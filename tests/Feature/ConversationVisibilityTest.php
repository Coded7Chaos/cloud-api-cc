<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConversationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private static int $n = 0;

    private function conversationWithInboundAt(\DateTimeInterface $sentAt, ?int $assignedUserId = null): Conversation
    {
        self::$n++;
        $contact = Contact::create(['wa_id' => '52155000'.self::$n, 'profile_name' => 'Cliente '.self::$n]);
        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $assignedUserId,
            'status' => 'open',
            'last_message_at' => $sentAt,
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid-'.Str::uuid(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hola',
            'status' => 'delivered',
            'sent_at' => $sentAt,
        ]);

        return $conversation;
    }

    private function agent(): User
    {
        $agent = User::factory()->soporte()->create();
        $this->scheduleAgent($agent);

        return $agent;
    }

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    private function scheduleAgent(User $agent, ?Carbon $now = null): void
    {
        $now ??= now();

        app(ScheduleService::class)->createSchedule($agent, [
            ['weekday' => $now->dayOfWeekIso, 'start_time' => '00:00', 'end_time' => '23:59'],
        ], $now->copy()->startOfMonth());
    }

    public function test_soporte_sees_only_unclaimed_and_own_conversations(): void
    {
        $me = $this->agent();
        $someoneElse = $this->agent();
        $mine = $this->conversationWithInboundAt(now()->subHour(), $me->id);
        $unclaimed = $this->conversationWithInboundAt(now()->subHour(), null);
        $theirs = $this->conversationWithInboundAt(now()->subHour(), $someoneElse->id);

        $this->actingAs($me);
        $ids = collect($this->getJson('/api/conversations')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($unclaimed->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_administrador_sees_every_conversation(): void
    {
        $admin = $this->admin();
        $a = $this->conversationWithInboundAt(now()->subHour(), $this->agent()->id);
        $b = $this->conversationWithInboundAt(now()->subHour(), $this->agent()->id);
        $c = $this->conversationWithInboundAt(now()->subHour(), null);

        $this->actingAs($admin);
        $ids = collect($this->getJson('/api/conversations')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
        $this->assertTrue($ids->contains($c->id));
    }

    public function test_two_agents_racing_for_an_unclaimed_conversation_only_one_wins(): void
    {
        $agentA = $this->agent();
        $agentB = $this->agent();
        $conversation = $this->conversationWithInboundAt(now()->subMinutes(5), null);

        $this->actingAs($agentA);
        $this->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $agentA->id);

        // Un 2do request SECUENCIAL después de que A ya se la quedó ve el
        // assigned_user_id ya escrito -> 404 ("ya es de otro"), no 409. Eso
        // se prueba en test_soporte_cannot_open_a_conversation_assigned_to_someone_else.
        // Para probar la carrera DE VERDAD (dos requests simultáneos, cada
        // uno leyendo assigned_user_id=null ANTES de que el otro escriba)
        // hace falta simular el estado stale a mano: dos instancias del
        // modelo cargadas antes de que cualquiera de las dos escriba.
        $conversation2 = $this->conversationWithInboundAt(now()->subMinutes(5), null);
        $asSeenByA = Conversation::find($conversation2->id);
        $asSeenByB = Conversation::find($conversation2->id);

        $asSeenByA->authorizeAndClaimFor($agentA);
        $this->assertSame($agentA->id, $asSeenByA->assigned_user_id);

        try {
            $asSeenByB->authorizeAndClaimFor($agentB);
            $this->fail('Se esperaba un HttpException 409 (carrera perdida).');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame($agentA->id, $conversation2->fresh()->assigned_user_id);
    }

    public function test_administrador_viewing_unclaimed_conversation_does_not_assign_it(): void
    {
        $admin = $this->admin();
        $conversation = $this->conversationWithInboundAt(now()->subMinutes(5), null);

        $this->actingAs($admin);
        $this->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee', null);

        $this->assertNull($conversation->fresh()->assigned_user_id);
    }

    public function test_soporte_cannot_open_a_conversation_assigned_to_someone_else(): void
    {
        $agentA = $this->agent();
        $conversation = $this->conversationWithInboundAt(now()->subMinutes(5), $this->agent()->id);

        $this->actingAs($agentA);
        $this->getJson("/api/conversations/{$conversation->id}")->assertNotFound();
    }

    public function test_soporte_cannot_send_to_a_conversation_assigned_to_someone_else(): void
    {
        $agentA = $this->agent();
        $conversation = $this->conversationWithInboundAt(now()->subMinutes(5), $this->agent()->id);

        $this->actingAs($agentA);
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Intento colado'])
            ->assertNotFound();

        // Solo sigue existiendo el entrante inicial: no se coló ningún saliente.
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_closed_window_conversation_is_archived_and_hidden_from_default_list(): void
    {
        $agent = $this->agent();
        $expired = $this->conversationWithInboundAt(now()->subHours(25), $agent->id);
        $active = $this->conversationWithInboundAt(now()->subHours(1), $agent->id);

        $this->actingAs($agent);

        $activeRes = $this->getJson('/api/conversations')->assertOk();
        $activeIds = collect($activeRes->json('data'))->pluck('id');
        $this->assertFalse($activeIds->contains($expired->id));
        $this->assertTrue($activeIds->contains($active->id));
        $activeRes->assertJsonPath('meta.archived_count', 1);

        $archivedIds = collect($this->getJson('/api/conversations?archived=1')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($archivedIds->contains($expired->id));
        $this->assertFalse($archivedIds->contains($active->id));
    }

    public function test_archived_list_can_be_filtered_by_registration_date(): void
    {
        $agent = $this->agent();

        $fromJune = $this->conversationWithInboundAt(now()->subDays(40), $agent->id);
        $fromJune->forceFill(['created_at' => '2026-06-20 10:00:00'])->save();

        $fromJuly = $this->conversationWithInboundAt(now()->subDays(10), $agent->id);
        $fromJuly->forceFill(['created_at' => '2026-07-12 15:00:00'])->save();

        $this->actingAs($agent);

        $juneOnly = collect(
            $this->getJson('/api/conversations?archived=1&date=2026-06-20')->assertOk()->json('data'),
        )->pluck('id');
        $this->assertTrue($juneOnly->contains($fromJune->id));
        $this->assertFalse($juneOnly->contains($fromJuly->id));

        $julyOnly = collect(
            $this->getJson('/api/conversations?archived=1&date=2026-07-12')->assertOk()->json('data'),
        )->pluck('id');
        $this->assertTrue($julyOnly->contains($fromJuly->id));
        $this->assertFalse($julyOnly->contains($fromJune->id));

        // Sin filtro de fecha, aparecen los dos archivados.
        $both = collect(
            $this->getJson('/api/conversations?archived=1')->assertOk()->json('data'),
        )->pluck('id');
        $this->assertTrue($both->contains($fromJune->id) && $both->contains($fromJuly->id));
    }

    public function test_viewing_an_unclaimed_expired_conversation_does_not_assign_it(): void
    {
        $agent = $this->agent();
        $conversation = $this->conversationWithInboundAt(now()->subHours(30), null);

        $this->actingAs($agent);
        $this->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee', null);

        $this->assertNull($conversation->fresh()->assigned_user_id);
    }

    public function test_soporte_outside_working_hours_does_not_see_or_claim_unassigned_conversations(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 12, 0));
        $agent = User::factory()->soporte()->create();
        app(ScheduleService::class)->createSchedule($agent, [
            ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00'],
        ], Carbon::create(2026, 7, 1));
        $unclaimed = $this->conversationWithInboundAt(now()->subMinutes(5), null);

        $this->actingAs($agent);

        $ids = collect($this->getJson('/api/conversations')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($unclaimed->id));

        $this->getJson("/api/conversations/{$unclaimed->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo puedes tomar chats nuevos durante tu horario laboral.');

        $this->assertNull($unclaimed->fresh()->assigned_user_id);
    }
}
