<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La superficie que consumen las apps móviles: token Bearer en vez de cookie.
 *
 * Estos tests son el contrato con la app Flutter, que ya está escrita contra
 * él: manda Authorization: Bearer y espera {token, user} al loguearse.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_login_devuelve_token_y_permite_usarlo_como_bearer(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/login', [
            'email' => 'agente@cc.test',
            'password' => 'password',
            'device_name' => 'iPhone de Dipali',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // El AuthBloc de Flutter lee data['user'] con sus permisos adentro.
        $login->assertJsonStructure(['token', 'user' => ['id', 'email', 'role' => ['permissions']]]);

        // Sin token no se pasa; con el token sí. Ninguna cookie de por medio.
        $this->getJson('/api/v1/user')->assertUnauthorized();

        $this->withToken($token)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'agente@cc.test');

        $this->withToken($token)->getJson('/api/v1/conversations')->assertOk();
    }

    public function test_login_sin_device_name_tambien_da_token_cuando_no_hay_sesion(): void
    {
        // La app Flutter tal como está escrita hoy no manda device_name; sin
        // sesión disponible no hay cookie que sostenga el login, así que el
        // token tiene que salir igual.
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'agente@cc.test',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_credenciales_invalidas_no_emiten_token(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/login', [
            'email' => 'agente@cc.test',
            'password' => 'la-que-no-es',
            'device_name' => 'iPhone',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_usuario_con_invitacion_pendiente_no_puede_sacar_token(): void
    {
        // password NULL = invitación sin aceptar. No debe poder loguearse
        // mandando cualquier cosa como contraseña.
        $this->seed(DatabaseSeeder::class);

        $invited = User::factory()->create([
            'email' => 'invitado@cc.test',
            'password' => null,
            'role_id' => User::where('email', 'soporte@cc.test')->value('role_id'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $invited->email,
            'password' => 'lo-que-sea',
            'device_name' => 'Android',
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_relogin_en_el_mismo_dispositivo_reemplaza_el_token_anterior(): void
    {
        $this->seed(DatabaseSeeder::class);

        $credentials = [
            'email' => 'agente@cc.test',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ];

        $first = $this->postJson('/api/v1/login', $credentials)->json('token');
        $second = $this->postJson('/api/v1/login', $credentials)->json('token');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // El viejo queda muerto, no conviviendo con el nuevo.
        $this->withToken($first)->getJson('/api/v1/user')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/v1/user')->assertOk();
    }

    public function test_logout_revoca_solo_el_token_del_dispositivo_que_lo_pide(): void
    {
        $this->seed(DatabaseSeeder::class);

        $phone = $this->postJson('/api/v1/login', [
            'email' => 'agente@cc.test', 'password' => 'password', 'device_name' => 'iPhone',
        ])->json('token');

        $tablet = $this->postJson('/api/v1/login', [
            'email' => 'agente@cc.test', 'password' => 'password', 'device_name' => 'iPad',
        ])->json('token');

        $this->withToken($phone)->postJson('/api/v1/logout')->assertOk();
        $this->forgetResolvedGuards();

        $this->withToken($phone)->getJson('/api/v1/user')->assertUnauthorized();
        $this->withToken($tablet)->getJson('/api/v1/user')->assertOk();
    }

    /**
     * El guard de sanctum cachea el usuario que ya resolvió, y en los tests el
     * contenedor es el mismo entre request y request. Sin esto, una petición
     * hecha DESPUÉS de revocar un token seguiría viendo al usuario cacheado y
     * el assert pasaría (o fallaría) por el motivo equivocado. En producción no
     * hace falta: cada request arranca con el contenedor limpio.
     */
    private function forgetResolvedGuards(): void
    {
        $this->app->make('auth')->forgetGuards();
    }

    public function test_logout_da_de_baja_el_registro_de_push_de_ese_dispositivo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'agente@cc.test', 'password' => 'password', 'device_name' => 'iPhone',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/devices', [
            'token' => 'apns-token-abc',
            'platform' => 'ios',
        ])->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);

        // Deslogueado el teléfono, no le tenemos que seguir mandando pushes.
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_registro_de_dispositivo_es_idempotente(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        $this->actingAs($agent)
            ->postJson('/api/v1/devices', ['token' => 'fcm-xyz', 'platform' => 'android'])
            ->assertCreated();

        // Volver a registrar el mismo token (rearranque de la app) actualiza,
        // no duplica.
        $this->actingAs($agent)
            ->postJson('/api/v1/devices', [
                'token' => 'fcm-xyz',
                'platform' => 'android',
                'app_version' => '1.2.0',
            ])
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'token_hash' => DeviceToken::hashFor('fcm-xyz'),
            'app_version' => '1.2.0',
        ]);

        $this->actingAs($agent)
            ->deleteJson('/api/v1/devices', ['token' => 'fcm-xyz'])
            ->assertNoContent();

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_plataforma_desconocida_se_rechaza(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        $this->actingAs($agent)
            ->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'blackberry'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');
    }

    public function test_los_permisos_se_aplican_igual_por_token_que_por_sesion(): void
    {
        $this->seed(DatabaseSeeder::class);

        // "soporte" no tiene usuarios.ver ni auditoria.ver.
        $token = $this->postJson('/api/v1/login', [
            'email' => 'soporte@cc.test', 'password' => 'password', 'device_name' => 'Android',
        ])->json('token');

        $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/conversations')->assertOk();
    }

    public function test_el_spa_por_sesion_sigue_funcionando_sin_token(): void
    {
        // La regresión que más importa: exponer la API no puede haber roto al
        // panel web, que se autentica con la cookie de sesión.
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        $this->actingAs($agent)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'agente@cc.test');

        $this->actingAs($agent)->getJson('/api/conversations')->assertOk();

        // Y el login por sesión no emite token (no hay nada que guardar).
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_la_bandeja_pagina_solo_si_el_cliente_lo_pide(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        // Sin per_page: lista entera, como siempre (el SPA cuenta con esto).
        $full = $this->actingAs($agent)->getJson('/api/v1/conversations')->assertOk();
        $this->assertCount(5, $full->json('data'));
        $this->assertNull($full->json('meta.current_page'));

        $paged = $this->actingAs($agent)->getJson('/api/v1/conversations?per_page=2')->assertOk();
        $this->assertCount(2, $paged->json('data'));
        $this->assertSame(1, $paged->json('meta.current_page'));
        $this->assertSame(5, $paged->json('meta.total'));
        $this->assertSame(3, $paged->json('meta.last_page'));
    }

    public function test_updated_since_solo_trae_lo_que_cambio(): void
    {
        // El tiempo se fija a mano: updated_at se guarda con precisión de
        // segundo y el filtro es >= (inclusivo a propósito, para no perderse
        // un cambio ocurrido en el mismo segundo que el corte). Sin controlar
        // el reloj, sembrar y consultar caen en el mismo segundo y el test
        // mediría el redondeo en vez del filtro.
        Carbon::setTestNow(Carbon::create(2026, 7, 21, 10, 0));
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        Carbon::setTestNow(Carbon::create(2026, 7, 21, 10, 1));
        $first = $this->actingAs($agent)->getJson('/api/v1/conversations')->assertOk();
        $syncedAt = $first->json('meta.synced_at');
        $this->assertIsString($syncedAt);
        $this->assertCount(5, $first->json('data'));

        // Nada cambió desde entonces: el polleo vuelve vacío y barato.
        $this->actingAs($agent)
            ->getJson('/api/v1/conversations?updated_since='.urlencode($syncedAt))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Ahora sí toco una conversación.
        Carbon::setTestNow(Carbon::create(2026, 7, 21, 10, 2));
        $conversation = $agent->assignedConversations()->firstOrFail();
        $conversation->touch();

        $this->actingAs($agent)
            ->getJson('/api/v1/conversations?updated_since='.urlencode($syncedAt))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id);
    }

    public function test_el_detalle_acotado_trae_los_mensajes_mas_recientes_en_orden(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        // El hilo sembrado con más mensajes tiene 3.
        $conversation = $agent->assignedConversations()->withCount('messages')
            ->orderByDesc('messages_count')->firstOrFail();

        $full = $this->actingAs($agent)->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()->json('data.messages');
        $this->assertCount(3, $full);

        $limited = $this->actingAs($agent)
            ->getJson("/api/v1/conversations/{$conversation->id}?messages_limit=2")
            ->assertOk()->json('data');

        // Los DOS ÚLTIMOS del hilo, todavía en orden cronológico.
        $this->assertCount(2, $limited['messages']);
        $this->assertSame(
            array_column(array_slice($full, -2), 'id'),
            array_column($limited['messages'], 'id'),
        );

        // Y el cursor apunta al más viejo de la tanda, para pedir lo anterior.
        $this->assertSame($limited['messages'][0]['id'], $limited['messages_cursor']);
    }

    public function test_el_historial_paginado_camina_hacia_atras_sin_repetir(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        $conversation = $agent->assignedConversations()->withCount('messages')
            ->orderByDesc('messages_count')->firstOrFail();

        $all = $this->actingAs($agent)->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()->json('data.messages');
        $ids = array_column($all, 'id');

        $page = $this->actingAs($agent)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?limit=2")
            ->assertOk();

        $this->assertSame(array_slice($ids, -2), array_column($page->json('data'), 'id'));
        $this->assertTrue($page->json('meta.has_more'));

        $older = $this->actingAs($agent)->getJson(
            "/api/v1/conversations/{$conversation->id}/messages?limit=2&before=".$page->json('meta.next_cursor'),
        )->assertOk();

        $this->assertSame(array_slice($ids, 0, 1), array_column($older->json('data'), 'id'));
        $this->assertFalse($older->json('meta.has_more'));
    }
}
