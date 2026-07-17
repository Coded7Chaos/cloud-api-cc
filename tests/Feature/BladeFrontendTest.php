<?php

namespace Tests\Feature;

use App\Models\Tarea;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_see_login_and_authenticate_through_blade_form(): void
    {
        $agent = User::factory()->create(['password' => 'Password123']);

        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
        $this->post('/login', ['email' => $agent->email, 'password' => 'Password123'])
            ->assertRedirect(route('agent.dashboard'));
    }

    public function test_agent_can_render_their_blade_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();
        $agent->update(['is_admin' => false]);

        $this->actingAs($agent)->get('/agente')->assertOk()->assertSee('Mi panel de agente');
        $this->actingAs($agent)->get('/agente/historial')->assertOk()->assertSee('Mi historial');
        $this->actingAs($agent)->get('/chats')->assertOk()->assertSee('Chats');
        $this->actingAs($agent)->get('/admin/usuarios')->assertForbidden();
    }

    public function test_admin_can_render_every_admin_blade_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $agent = User::factory()->create();
        $task = Tarea::create(['titulo' => 'Seguimiento real']);
        $task->usuarios()->attach($agent, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->assertSee('Usuarios');
        $this->actingAs($admin)->get('/admin/horarios')->assertOk()->assertSee('Horarios');
        $this->actingAs($admin)->get('/admin/tareas/create')->assertOk()->assertSee('Crear nueva tarea');
        $this->actingAs($admin)->get('/admin/seguimiento')
            ->assertOk()
            ->assertSee('Seguimiento de agentes')
            ->assertSee('Seguimiento real')
            ->assertSee('Realizada');
        $this->actingAs($admin)->get('/agente')->assertForbidden();
    }
}
