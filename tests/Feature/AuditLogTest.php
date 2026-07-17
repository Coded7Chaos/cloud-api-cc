<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_view_user_audit_logs_with_filters(): void
    {
        Notification::fake();

        $admin = User::factory()->administrador()->create(['name' => 'Admin']);
        $this->actingAs($admin);

        $this->postJson('/api/users', ['email' => 'auditado@cc.test'])
            ->assertCreated();

        $user = User::where('email', 'auditado@cc.test')->firstOrFail();
        Notification::assertSentTo($user, UserInvitationNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'usuarios',
            'action' => 'usuario_creado',
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'target_email' => 'auditado@cc.test',
        ]);

        $this->getJson('/api/audit-logs?category=usuarios')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'usuarios')
            ->assertJsonPath('data.0.action', 'usuario_creado')
            ->assertJsonPath('data.0.target.email', 'auditado@cc.test');
    }

    public function test_support_cannot_view_audit_logs(): void
    {
        $support = User::factory()->soporte()->create();

        $this->actingAs($support);

        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_schedule_updates_are_audited_with_actor_and_affected_user(): void
    {
        $admin = User::factory()->administrador()->create(['name' => 'Admin']);
        $support = User::factory()->soporte()->create(['name' => 'Agente', 'last_name' => 'Uno']);

        $this->actingAs($admin);

        $this->putJson("/api/users/{$support->id}/schedule", [
            'shifts' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'horarios',
            'action' => 'horario_creado',
            'actor_user_id' => $admin->id,
            'target_user_id' => $support->id,
            'target_name' => 'Agente Uno',
        ]);
    }
}
