<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_schedule_list_only_includes_support_agents(): void
    {
        $admin = User::factory()->administrador()->create(['name' => 'Admin']);
        $support = User::factory()->soporte()->create(['name' => 'Soporte']);

        $this->actingAs($admin);

        $ids = collect($this->getJson('/api/schedules')->assertOk()->json('data'))
            ->pluck('user.id');

        $this->assertTrue($ids->contains($support->id));
        $this->assertFalse($ids->contains($admin->id));
    }

    public function test_administrators_cannot_receive_schedule_assignments(): void
    {
        $admin = User::factory()->administrador()->create();
        $otherAdmin = User::factory()->administrador()->create();

        $this->actingAs($admin);

        $this->putJson("/api/users/{$otherAdmin->id}/schedule", [
            'shifts' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo se pueden asignar horarios a agentes de soporte.');

        $this->assertDatabaseMissing('schedule_versions', ['user_id' => $otherAdmin->id]);
    }

    public function test_support_agents_can_receive_schedule_assignments(): void
    {
        $admin = User::factory()->administrador()->create();
        $support = User::factory()->soporte()->create();

        $this->actingAs($admin);

        $this->putJson("/api/users/{$support->id}/schedule", [
            'shifts' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('schedule_versions', ['user_id' => $support->id]);
    }

    public function test_support_role_cannot_access_schedule_management_api(): void
    {
        $support = User::factory()->soporte()->create();

        $this->actingAs($support);

        $this->getJson('/api/schedules')->assertForbidden();
        $this->putJson("/api/users/{$support->id}/schedule", [
            'shifts' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00'],
            ],
        ])->assertForbidden();
    }
}
