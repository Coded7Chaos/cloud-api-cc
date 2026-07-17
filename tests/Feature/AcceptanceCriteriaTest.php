<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceCriteriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_closes_chats_inactive_since_their_last_message(): void
    {
        $old = $this->conversation([
            'created_at' => now()->subDays(3),
            'last_message_at' => now()->subHours(25),
        ]);
        $recentMessage = $this->conversation([
            'created_at' => now()->subDays(3),
            'last_message_at' => now()->subHour(),
        ]);

        $this->artisan('chats:cerrar-inactivos')->assertSuccessful();

        $this->assertSame('closed', $old->fresh()->status);
        $this->assertSame('open', $recentMessage->fresh()->status);
    }

    public function test_sending_uses_last_message_time_and_closes_an_inactive_chat(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversation([
            'assigned_user_id' => $user->id,
            'created_at' => now()->subDays(3),
            'last_message_at' => now()->subHours(25),
        ]);

        $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hola'])
            ->assertUnprocessable();

        $this->assertSame('closed', $conversation->fresh()->status);
    }

    public function test_only_admins_can_create_and_assign_tasks(): void
    {
        $agent = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $assigned = User::factory()->create();

        $this->actingAs($agent)->postJson('/api/tareas', [
            'titulo' => 'No autorizada',
            'usuarios' => [$assigned->id],
        ])->assertForbidden();

        $this->actingAs($admin)->postJson('/api/tareas', [
            'titulo' => 'Seguimiento',
            'descripcion' => 'Contactar al cliente',
            'usuarios' => [$agent->id, $assigned->id],
        ])->assertCreated();

        $tareaId = (int) Tarea::query()->value('id');
        $this->assertDatabaseHas('tarea_user', ['tarea_id' => $tareaId, 'user_id' => $agent->id]);
        $this->assertDatabaseHas('tarea_user', ['tarea_id' => $tareaId, 'user_id' => $assigned->id]);
    }

    public function test_agent_summary_contains_their_schedule_tasks_and_all_chats(): void
    {
        $agent = User::factory()->create();
        $other = User::factory()->create();
        $task = Tarea::create(['titulo' => 'Mi tarea']);
        $task->usuarios()->attach($agent);
        Tarea::create(['titulo' => 'Tarea ajena'])->usuarios()->attach($other);
        $version = $agent->scheduleVersions()->create(['effective_from' => today()]);
        $version->shifts()->create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);
        $mine = $this->conversation(['assigned_user_id' => $agent->id]);
        $otherChat = $this->conversation(['assigned_user_id' => $other->id]);
        Message::create([
            'conversation_id' => $otherChat->id,
            'wa_message_id' => 'agent-reply-other-chat',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Respuesta del agente en un chat ajeno',
            'status' => 'sent',
            'sender_user_id' => $agent->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($agent)->getJson('/api/agente/resumen')->assertOk();

        $response->assertJsonPath('data.schedule.shifts.0.weekday', 1)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.titulo', 'Mi tarea')
            ->assertJsonCount(2, 'data.chats')
            ->assertJsonFragment(['id' => $mine->id]);

        $this->actingAs($agent)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $otherChat->id]);

        $this->actingAs($agent)
            ->getJson("/api/conversations/{$otherChat->id}")
            ->assertOk();

        $this->actingAs($agent)
            ->getJson('/api/agente/historial')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $mine->id])
            ->assertJsonFragment([
                'id' => $otherChat->id,
                'sent_messages_count' => 1,
            ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->getJson('/api/agente/historial')
            ->assertForbidden();
    }

    public function test_agent_can_complete_an_assigned_task_and_admin_can_track_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $agent = User::factory()->create();
        $task = Tarea::create(['titulo' => 'Llamar al cliente']);
        $task->usuarios()->attach($agent);

        $this->actingAs($agent)
            ->patchJson("/api/agente/tareas/{$task->id}/completar")
            ->assertOk();

        $this->assertDatabaseHas('tarea_user', [
            'tarea_id' => $task->id,
            'user_id' => $agent->id,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/seguimiento')
            ->assertOk()
            ->assertJsonPath('data.task_assignments.0.status', 'completed')
            ->assertJsonPath('data.task_assignments.0.user.id', $agent->id);
    }

    private function conversation(array $attributes = []): Conversation
    {
        $contact = Contact::create([
            'wa_id' => fake()->unique()->numerify('#############'),
            'profile_name' => 'Contacto',
        ]);

        return Conversation::create(array_merge([
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
        ], $attributes));
    }
}
