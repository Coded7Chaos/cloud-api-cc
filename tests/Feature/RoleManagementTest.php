<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_create_a_role_with_permissions(): void
    {
        $admin = User::factory()->administrador()->create();
        $permissionIds = Permission::whereIn('name', ['conversaciones.ver', 'conversaciones.responder'])
            ->pluck('id')->all();

        $this->actingAs($admin)
            ->postJson('/api/roles', ['name' => 'Supervisor', 'permissions' => $permissionIds])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Supervisor')
            ->assertJsonPath('data.is_protected', false)
            ->assertJsonPath('data.users_count', 0)
            ->assertJsonCount(2, 'data.permissions');

        $role = Role::where('name', 'Supervisor')->firstOrFail();
        $this->assertEqualsCanonicalizing($permissionIds, $role->permissions->pluck('id')->all());
    }

    public function test_admin_can_replace_the_permissions_of_a_role(): void
    {
        $admin = User::factory()->administrador()->create();
        $role = Role::create(['name' => 'Supervisor']);
        $role->permissions()->sync(Permission::where('name', 'conversaciones.ver')->pluck('id'));
        $newPermissionId = Permission::where('name', 'auditoria.ver')->value('id');

        $this->actingAs($admin)
            ->putJson("/api/roles/{$role->id}", ['name' => 'Supervisor', 'permissions' => [$newPermissionId]])
            ->assertOk()
            ->assertJsonCount(1, 'data.permissions');

        $this->assertSame([$newPermissionId], $role->fresh()->permissions->pluck('id')->all());
    }

    public function test_deleting_a_role_leaves_its_users_without_a_role(): void
    {
        $admin = User::factory()->administrador()->create();
        $role = Role::create(['name' => 'Supervisor']);
        $member = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.users_left_without_role', 1);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        // El restrictOnDelete de la FK obliga a soltar al usuario primero; queda sin rol, no borrado.
        $this->assertDatabaseHas('users', ['id' => $member->id, 'role_id' => null]);
    }

    public function test_system_roles_cannot_be_deleted_or_renamed(): void
    {
        $admin = User::factory()->administrador()->create();
        $soporte = Role::where('name', 'soporte')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson("/api/roles/{$soporte->id}")
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->putJson("/api/roles/{$soporte->id}", ['name' => 'otro-nombre'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $soporte->id, 'name' => 'soporte']);
    }

    public function test_a_user_cannot_delete_their_own_role(): void
    {
        $role = Role::create(['name' => 'Supervisor']);
        $role->permissions()->sync(Permission::whereIn('name', ['roles.ver', 'roles.eliminar'])->pluck('id'));
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_non_admin_cannot_manage_roles(): void
    {
        $agent = User::factory()->soporte()->create();

        $this->actingAs($agent)->getJson('/api/roles')->assertForbidden();
        $this->actingAs($agent)->getJson('/api/permissions')->assertForbidden();
        $this->actingAs($agent)->postJson('/api/roles', ['name' => 'X'])->assertForbidden();
    }

    public function test_permission_catalog_lists_every_permission(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)
            ->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonCount(Permission::count(), 'data')
            ->assertJsonPath('data.0.name', Permission::orderBy('name')->value('name'));
    }
}
