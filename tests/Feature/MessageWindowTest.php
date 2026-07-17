<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageWindowTest extends TestCase
{
    use RefreshDatabase;

    private function conversationWithLastInboundAt(\DateTimeInterface $sentAt): Conversation
    {
        $contact = Contact::create(['wa_id' => '5215500000000', 'profile_name' => 'Cliente de prueba']);
        $conversation = Conversation::create(['contact_id' => $contact->id, 'status' => 'open']);

        Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid-inbound-'.Str::uuid(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hola',
            'status' => 'delivered',
            'sent_at' => $sentAt,
        ]);

        return $conversation;
    }

    private function actingAsAgent(): User
    {
        $this->seed(RoleSeeder::class);
        $agent = User::factory()->soporte()->create();
        app(ScheduleService::class)->createSchedule($agent, [
            ['weekday' => now()->dayOfWeekIso, 'start_time' => '00:00', 'end_time' => '23:59'],
        ], now()->copy()->startOfMonth());
        $this->actingAs($agent);

        return $agent;
    }

    public function test_can_send_within_the_24_hour_window(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT1']]], 200)]);

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hola, ¿en qué te ayudo?'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'direction' => 'outbound']);
    }

    public function test_cannot_send_after_the_24_hour_window_expires(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(25));

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Llego tarde'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, '24 horas'));

        // El guard corta antes de crear nada: sigue habiendo un solo mensaje (el entrante inicial).
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_window_reopens_after_a_new_inbound_message(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(30));

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Ya no debería poder'])
            ->assertStatus(422);

        // El cliente vuelve a escribir -> se reabre la ventana desde ese momento.
        Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid-inbound-'.Str::uuid(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Sigo ahí',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT2']]], 200)]);

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Ahora sí puedo'])
            ->assertCreated();
    }

    public function test_can_send_image_with_optional_caption(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_IMAGE_1'], 200)
            ->push(['messages' => [['id' => 'wamid.IMAGE1']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Mira esta imagen',
            'media' => UploadedFile::fake()->image('foto.jpg', 800, 600),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.IMAGE1',
            'direction' => 'outbound',
            'type' => 'image',
            'body' => 'Mira esta imagen',
        ]);
        $this->assertDatabaseHas('message_media', [
            'mime_type' => 'image/jpeg',
            'wa_media_id' => 'MEDIA_IMAGE_1',
        ]);
    }

    public function test_can_send_video_with_optional_caption(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_VIDEO_1'], 200)
            ->push(['messages' => [['id' => 'wamid.VIDEO1']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Mira este video',
            'media' => UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.VIDEO1',
            'direction' => 'outbound',
            'type' => 'video',
            'body' => 'Mira este video',
        ]);
        $this->assertDatabaseHas('message_media', [
            'mime_type' => 'video/mp4',
            'wa_media_id' => 'MEDIA_VIDEO_1',
        ]);
    }
}
