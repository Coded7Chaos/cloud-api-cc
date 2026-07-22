<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tarea;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_support_dashboard_only_contains_the_authenticated_users_data(): void
    {
        $agent = User::factory()->soporte()->create(['name' => 'Agente', 'last_name' => 'Actual']);
        $otherAgent = User::factory()->soporte()->create(['name' => 'Agente', 'last_name' => 'Ajeno']);

        $pendingTask = Tarea::create(['titulo' => 'Mi tarea pendiente']);
        $pendingTask->usuarios()->attach($agent);
        $completedTask = Tarea::create(['titulo' => 'Mi tarea realizada']);
        $completedTask->usuarios()->attach($agent, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        Tarea::create(['titulo' => 'Tarea ajena'])->usuarios()->attach($otherAgent);

        $version = $agent->scheduleVersions()->create(['effective_from' => today()]);
        $version->shifts()->create([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $mine = $this->conversation([
            'assigned_user_id' => $agent->id,
            'status' => 'open',
        ], 'Contacto propio');
        $other = $this->conversation([
            'assigned_user_id' => $otherAgent->id,
            'status' => 'open',
        ], 'Contacto ajeno');

        $response = $this->actingAs($agent)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'soporte')
            ->assertJsonPath('data.summary.assigned_chats', 1)
            ->assertJsonPath('data.summary.active_chats', 1)
            ->assertJsonPath('data.summary.pending_tasks', 1)
            ->assertJsonPath('data.summary.completed_tasks', 1)
            ->assertJsonPath('data.schedule.shifts.0.weekday', 1)
            ->assertJsonCount(2, 'data.tasks')
            ->assertJsonCount(1, 'data.recent_conversations')
            ->assertJsonPath('data.recent_conversations.0.id', $mine->id)
            ->assertJsonMissingPath('data.agent_activity');

        $this->assertNotContains($other->id, collect($response->json('data.recent_conversations'))->pluck('id'));
        $this->assertNotContains('Tarea ajena', collect($response->json('data.tasks'))->pluck('titulo'));
    }

    public function test_admin_dashboard_contains_global_metrics_and_activity_by_support_agent(): void
    {
        $admin = User::factory()->administrador()->create();
        $firstAgent = User::factory()->soporte()->create([
            'name' => 'Ana',
            'last_name' => 'Agente',
            'email' => 'ana@cc.test',
        ]);
        $secondAgent = User::factory()->soporte()->create([
            'name' => 'Bruno',
            'last_name' => 'Agente',
            'email' => 'bruno@cc.test',
        ]);

        $assigned = $this->conversation([
            'assigned_user_id' => $firstAgent->id,
            'status' => 'open',
        ], 'Cliente activo');
        $this->conversation([
            'assigned_user_id' => null,
            'status' => 'closed',
        ], 'Cliente cerrado');

        Message::create([
            'conversation_id' => $assigned->id,
            'wa_message_id' => 'dashboard-admin-response',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Respuesta del agente',
            'status' => 'sent',
            'sender_user_id' => $firstAgent->id,
            'sent_at' => now(),
        ]);

        $completed = Tarea::create(['titulo' => 'Tarea completada']);
        $completed->usuarios()->attach($firstAgent, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        Tarea::create(['titulo' => 'Tarea pendiente'])->usuarios()->attach($secondAgent);

        $this->actingAs($admin)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'administrador')
            ->assertJsonPath('data.summary.agents', 2)
            ->assertJsonPath('data.summary.total_chats', 2)
            ->assertJsonPath('data.summary.active_chats', 1)
            ->assertJsonPath('data.summary.unassigned_chats', 1)
            ->assertJsonPath('data.summary.pending_tasks', 1)
            ->assertJsonPath('data.summary.completed_tasks', 1)
            ->assertJsonCount(2, 'data.agent_activity')
            ->assertJsonPath('data.agent_activity.0.id', $firstAgent->id)
            ->assertJsonPath('data.agent_activity.0.chats_count', 1)
            ->assertJsonPath('data.agent_activity.0.active_chats_count', 1)
            ->assertJsonPath('data.agent_activity.0.responses_count', 1)
            ->assertJsonPath('data.agent_activity.0.completed_tasks_count', 1)
            ->assertJsonPath('data.agent_activity.1.id', $secondAgent->id)
            ->assertJsonPath('data.agent_activity.1.pending_tasks_count', 1)
            ->assertJsonMissingPath('data.schedule');
    }

    public function test_dashboard_requires_authentication_and_is_available_in_v1(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();

        $agent = User::factory()->soporte()->create();
        $this->actingAs($agent)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'soporte');
    }

    /** @param array<string, mixed> $attributes */
    private function conversation(array $attributes, string $contactName): Conversation
    {
        $contact = Contact::create([
            'wa_id' => fake()->unique()->numerify('#############'),
            'profile_name' => $contactName,
        ]);

        return Conversation::create(array_merge([
            'contact_id' => $contact->id,
            'last_message_at' => now(),
        ], $attributes));
    }
}
