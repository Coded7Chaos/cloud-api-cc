<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Services\AuditLogService;
use App\Services\ScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAndUserTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_access_protected_api(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_with_valid_credentials_returns_user(): void
    {
        $user = User::factory()->create(['email' => 'agente@cc.test', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'agente@cc.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.email', 'agente@cc.test');
    }

    public function test_login_is_allowed_outside_configured_working_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 8, 0));
        $user = User::factory()->create(['email' => 'agente@cc.test', 'password' => 'secret123']);
        app(ScheduleService::class)->createSchedule($user, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ], Carbon::create(2026, 7, 1));

        $this->postJson('/api/login', ['email' => 'agente@cc.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.email', 'agente@cc.test');
    }

    public function test_login_is_allowed_inside_configured_working_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 10, 0));
        $user = User::factory()->create(['email' => 'agente@cc.test', 'password' => 'secret123']);
        app(ScheduleService::class)->createSchedule($user, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ], Carbon::create(2026, 7, 1));

        $this->postJson('/api/login', ['email' => 'agente@cc.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.email', 'agente@cc.test');
    }

    public function test_authenticated_api_is_allowed_outside_configured_working_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 8, 0));
        $user = User::factory()->create();
        app(ScheduleService::class)->createSchedule($user, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ], Carbon::create(2026, 7, 1));

        $this->actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/logout')->assertOk();
    }

    public function test_login_with_wrong_password_fails_validation(): void
    {
        User::factory()->create(['email' => 'agente@cc.test', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'agente@cc.test', 'password' => 'incorrecta'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_fetch_self_and_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->postJson('/api/logout')->assertOk();
    }

    public function test_agent_can_be_created(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());

        $roleId = Role::where('name', 'soporte')->value('id');

        $this->postJson('/api/users', [
            'name' => 'Nueva',
            'last_name' => 'Agente',
            'email' => 'nuevo@cc.test',
            'role_id' => $roleId,
        ])->assertCreated()
            ->assertJsonPath('message', 'Usuario creado. Enviamos una invitación para establecer su contraseña.');

        $user = User::where('email', 'nuevo@cc.test')->firstOrFail();

        $this->assertNull($user->password);
        $this->assertSame('Nueva', $user->name);
        $this->assertSame('Agente', $user->last_name);
        $this->assertSame($roleId, $user->role_id);
        Notification::assertSentTo($user, UserInvitationNotification::class);
    }

    public function test_creating_user_requires_name_last_name_and_role(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());

        $this->postJson('/api/users', ['email' => 'incompleto@cc.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'last_name', 'role_id']);
    }

    public function test_creating_user_with_duplicate_email_fails(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());
        User::factory()->create(['email' => 'existe@cc.test']);

        $this->postJson('/api/users', [
            'name' => 'Dup',
            'last_name' => 'Licado',
            'email' => 'existe@cc.test',
            'role_id' => Role::where('name', 'soporte')->value('id'),
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_invited_user_can_set_initial_password(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->soporte()->create([
            'email' => 'invitado@cc.test',
            'password' => null,
            'email_verified_at' => null,
        ]);
        $token = PasswordBroker::createToken($user);

        $this->getJson('/api/invitations/status?'.http_build_query([
            'email' => $user->email,
            'token' => $token,
        ]))->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->postJson('/api/invitations/accept', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Valid123!',
            'password_confirmation' => 'Valid123!',
        ])->assertOk()
            ->assertJsonPath('status', 'created');

        $user->refresh();
        $this->assertTrue(Hash::check('Valid123!', $user->password));
        $this->assertNotNull($user->email_verified_at);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Valid123!'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invitation_link_reports_already_set_when_password_exists(): void
    {
        $user = User::factory()->create(['email' => 'listo@cc.test', 'password' => 'secret123']);

        $this->getJson('/api/invitations/status?'.http_build_query([
            'email' => $user->email,
            'token' => 'token-cualquiera',
        ]))->assertOk()
            ->assertJsonPath('status', 'already_set');
    }

    public function test_deleted_user_is_anonymized_and_email_can_be_reused(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $me = User::factory()->administrador()->create();
        $other = User::factory()->soporte()->create([
            'name' => 'Agente',
            'last_name' => 'Eliminable',
            'email' => 'reutilizable@cc.test',
            'avatar_path' => 'avatars/eliminable.jpg',
        ]);
        Storage::disk('local')->put('avatars/eliminable.jpg', 'avatar');
        $other->createToken('android');
        PasswordBroker::createToken($other);
        app(ScheduleService::class)->createSchedule($other, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ], now());
        app(AuditLogService::class)->record(
            'usuarios',
            'usuario_editado',
            'Editó el usuario reutilizable@cc.test.',
            $me,
            $other,
            ['email' => 'reutilizable@cc.test', 'role' => 'soporte'],
        );
        $this->actingAs($me);

        // No puede eliminarse a sí mismo.
        $this->deleteJson("/api/users/{$me->id}")->assertStatus(422);

        // Elimina físicamente la cuenta y libera inmediatamente su correo.
        $this->deleteJson("/api/users/{$other->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $other->id,
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reutilizable@cc.test']);
        $this->assertDatabaseMissing('schedule_versions', ['user_id' => $other->id]);
        $this->assertDatabaseMissing('audit_logs', ['target_email' => 'reutilizable@cc.test']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'usuario_eliminado',
            'target_name' => 'Agente Eliminable',
            'target_email' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'usuario_editado',
            'target_name' => 'Agente Eliminable',
            'target_email' => null,
            'metadata' => null,
            'description' => 'Editó el usuario Agente Eliminable.',
        ]);
        Storage::disk('local')->assertMissing('avatars/eliminable.jpg');

        $this->postJson('/api/users', [
            'name' => 'Agente',
            'last_name' => 'Nuevo',
            'email' => 'reutilizable@cc.test',
            'role_id' => Role::where('name', 'soporte')->value('id'),
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'reutilizable@cc.test',
            'deleted_at' => null,
        ]);
    }

    public function test_cleanup_migration_frees_email_from_previously_soft_deleted_user(): void
    {
        $oldUser = User::factory()->create([
            'name' => 'Usuario',
            'last_name' => 'Anterior',
            'email' => 'anterior@cc.test',
        ]);
        app(AuditLogService::class)->record(
            'usuarios',
            'usuario_creado',
            'Creó el usuario anterior@cc.test.',
            target: $oldUser,
            metadata: ['email' => 'anterior@cc.test'],
        );
        $oldUser->delete();

        $migration = require database_path('migrations/2026_07_23_010000_purge_personal_data_from_deleted_users.php');
        $migration->up();

        $this->assertDatabaseMissing('users', ['id' => $oldUser->id]);
        $this->assertDatabaseHas('audit_logs', [
            'target_name' => 'Usuario Anterior',
            'target_email' => null,
            'metadata' => null,
            'description' => 'Creó el usuario Usuario Anterior.',
        ]);

        User::factory()->create(['email' => 'anterior@cc.test']);
        $this->assertDatabaseHas('users', [
            'email' => 'anterior@cc.test',
            'deleted_at' => null,
        ]);
    }

    public function test_support_role_cannot_access_user_management_api(): void
    {
        $this->seed(RoleSeeder::class);
        $support = User::factory()->soporte()->create();

        $this->actingAs($support);

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'email' => 'nodebe@cc.test',
        ])->assertForbidden();
    }
}
