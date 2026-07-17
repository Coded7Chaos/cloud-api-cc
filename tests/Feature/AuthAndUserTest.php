<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndUserTest extends TestCase
{
    use RefreshDatabase;

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
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());
        $soporteId = Role::where('name', 'soporte')->value('id');

        $this->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Agente',
            'email' => 'nuevo@cc.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role_id' => $soporteId,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'nuevo@cc.test', 'name' => 'Nuevo']);
    }

    public function test_creating_user_with_duplicate_email_fails(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());
        User::factory()->create(['email' => 'existe@cc.test']);
        $soporteId = Role::where('name', 'soporte')->value('id');

        $this->postJson('/api/users', [
            'name' => 'Otro',
            'last_name' => 'Agente',
            'email' => 'existe@cc.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role_id' => $soporteId,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_user_is_soft_deleted_and_cannot_delete_self(): void
    {
        $this->seed(RoleSeeder::class);
        $me = User::factory()->administrador()->create();
        $other = User::factory()->soporte()->create();
        $this->actingAs($me);

        // No puede eliminarse a sí mismo.
        $this->deleteJson("/api/users/{$me->id}")->assertStatus(422);

        // Elimina a otro -> soft delete (queda en la base con deleted_at).
        $this->deleteJson("/api/users/{$other->id}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $other->id]);
    }
}
