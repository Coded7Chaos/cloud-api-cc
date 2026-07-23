<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_update_their_own_name_and_email(): void
    {
        $user = User::factory()->soporte()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => 'Nuevo',
                'last_name' => 'Nombre',
                'email' => 'nuevo.correo@cc.test',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nuevo')
            ->assertJsonPath('user.email', 'nuevo.correo@cc.test');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'nuevo.correo@cc.test']);
    }

    public function test_email_must_not_belong_to_another_user(): void
    {
        $user = User::factory()->soporte()->create();
        User::factory()->create(['email' => 'ocupado@cc.test']);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => 'ocupado@cc.test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_keeping_your_own_email_is_allowed(): void
    {
        $user = User::factory()->soporte()->create(['email' => 'mio@cc.test']);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => 'Cambiado',
                'last_name' => $user->last_name,
                'email' => 'mio@cc.test',
            ])
            ->assertOk();
    }

    public function test_changing_password_requires_the_correct_current_one(): void
    {
        $user = User::factory()->soporte()->create(['password' => 'ClaveActual1!']);

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'incorrecta',
                'password' => 'ClaveNueva1!',
                'password_confirmation' => 'ClaveNueva1!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        // La contraseña no cambió.
        $this->assertTrue(Hash::check('ClaveActual1!', $user->fresh()->password));
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->soporte()->create(['password' => 'ClaveActual1!']);

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'ClaveActual1!',
                'password' => 'ClaveNueva1!',
                'password_confirmation' => 'ClaveNueva1!',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('ClaveNueva1!', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->soporte()->create(['password' => 'ClaveActual1!']);

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'ClaveActual1!',
                'password' => 'ClaveNueva1!',
                'password_confirmation' => 'noCoincide1!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_upload_and_serve_an_avatar(): void
    {
        Storage::fake('local');
        $user = User::factory()->soporte()->create();

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('yo.png', 200, 200),
            ])
            ->assertOk()
            ->assertJsonPath('user.avatar_url', fn ($url) => is_string($url) && str_contains($url, '/api/profile/avatar'));

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)->get('/api/profile/avatar')->assertOk();
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->soporte()->create();

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_user_can_remove_their_avatar(): void
    {
        Storage::fake('local');
        $user = User::factory()->soporte()->create();
        $this->actingAs($user)->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('yo.png'),
        ])->assertOk();
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)->deleteJson('/api/profile/avatar')->assertOk();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($path);
        $this->actingAs($user)->get('/api/profile/avatar')->assertNotFound();
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->putJson('/api/profile', ['name' => 'X'])->assertUnauthorized();
        $this->putJson('/api/profile/password', [])->assertUnauthorized();
        $this->postJson('/api/profile/avatar', [])->assertUnauthorized();
    }
}
