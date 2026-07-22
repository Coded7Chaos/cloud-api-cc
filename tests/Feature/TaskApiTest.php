<?php

namespace Tests\Feature;

use App\Models\Tarea;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_create_and_track_a_task_for_support_agents(): void
    {
        $admin = User::factory()->administrador()->create();
        $firstAgent = User::factory()->soporte()->create();
        $secondAgent = User::factory()->soporte()->create();

        $this->actingAs($admin)
            ->postJson('/api/tareas', [
                'titulo' => 'Responder pendientes',
                'descripcion' => 'Revisar los chats sin respuesta.',
                'usuarios' => [$firstAgent->id, $secondAgent->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.titulo', 'Responder pendientes')
            ->assertJsonCount(2, 'data.usuarios');

        $this->assertDatabaseHas('tareas', ['titulo' => 'Responder pendientes']);
        $this->assertDatabaseCount('tarea_user', 2);
        $this->assertDatabaseHas('tarea_user', [
            'user_id' => $firstAgent->id,
            'status' => 'pending',
            'completed_at' => null,
        ]);

        $this->getJson('/api/tareas')
            ->assertOk()
            ->assertJsonPath('data.0.titulo', 'Responder pendientes')
            ->assertJsonCount(2, 'data.0.usuarios')
            ->assertJsonCount(2, 'assignable_users');
    }

    public function test_support_only_sees_their_tasks_and_can_complete_them(): void
    {
        $agent = User::factory()->soporte()->create();
        $otherAgent = User::factory()->soporte()->create();
        $mine = Tarea::create(['titulo' => 'Mi tarea']);
        $other = Tarea::create(['titulo' => 'Tarea ajena']);
        $mine->usuarios()->attach($agent);
        $other->usuarios()->attach($otherAgent);

        $this->actingAs($agent)
            ->getJson('/api/tareas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonMissingPath('assignable_users');

        $this->patchJson("/api/tareas/{$mine->id}/completar")
            ->assertOk()
            ->assertJsonPath('message', 'Tarea marcada como realizada.');

        $this->assertDatabaseHas('tarea_user', [
            'tarea_id' => $mine->id,
            'user_id' => $agent->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull(
            $agent->tareas()->whereKey($mine->id)->firstOrFail()->pivot->completed_at,
        );

        $this->patchJson("/api/tareas/{$other->id}/completar")->assertNotFound();
    }

    public function test_task_permissions_and_assignable_role_are_enforced(): void
    {
        $admin = User::factory()->administrador()->create();
        $agent = User::factory()->soporte()->create();

        $this->actingAs($agent)
            ->postJson('/api/tareas', [
                'titulo' => 'No autorizada',
                'usuarios' => [$agent->id],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson('/api/tareas', [
                'titulo' => 'Asignación inválida',
                'usuarios' => [$admin->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usuarios');
    }

    public function test_task_routes_are_available_on_the_versioned_mobile_api(): void
    {
        $agent = User::factory()->soporte()->create();
        $task = Tarea::create(['titulo' => 'Desde móvil']);
        $task->usuarios()->attach($agent);

        $this->actingAs($agent)
            ->getJson('/api/v1/tareas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $task->id);

        $this->patchJson("/api/v1/tareas/{$task->id}/completar")->assertOk();
    }
}
