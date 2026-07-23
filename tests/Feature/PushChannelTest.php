<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\ApnsChannel;
use App\Services\Push\FcmChannel;
use App\Services\Push\PushMessage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Los emisores propios de push contra APNs y FCM.
 *
 * Lo que más se testea acá es la firma: el JWT se arma a mano con openssl y,
 * en ES256, hay que convertir la firma de DER a R||S crudo. Si esa conversión
 * está mal, Apple devuelve un 403 genérico y desde afuera parece un problema
 * de credenciales, así que se verifica criptográficamente en el test.
 */
class PushChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_apns_firma_un_jwt_es256_verificable_y_manda_el_payload_correcto(): void
    {
        [$privateKey, $publicKey] = $this->generateKey(OPENSSL_KEYTYPE_EC);

        config([
            'push.apns.key_id' => 'ABC1234567',
            'push.apns.team_id' => 'TEAM123456',
            'push.apns.bundle_id' => 'com.cloudapicc.panel',
            'push.apns.private_key' => $privateKey,
            'push.apns.private_key_path' => null,
            'push.apns.production' => false,
        ]);

        $user = $this->userWithDevice(DeviceToken::PLATFORM_IOS, 'apns-device-token');

        Http::fake(['api.sandbox.push.apple.com/*' => Http::response('', 200)]);

        (new ApnsChannel)->send($user, new PushMessage(
            'Nuevo mensaje de WhatsApp',
            '¿Tienen stock?',
            ['conversation_id' => 42, 'url' => '/chats'],
        ));

        Http::assertSent(function (Request $request) use ($publicKey) {
            $this->assertSame(
                'https://api.sandbox.push.apple.com/3/device/apns-device-token',
                $request->url(),
            );
            $this->assertSame('com.cloudapicc.panel', $request->header('apns-topic')[0]);
            $this->assertSame('alert', $request->header('apns-push-type')[0]);
            $this->assertSame('10', $request->header('apns-priority')[0]);

            $jwt = str_replace('bearer ', '', $request->header('authorization')[0]);
            $this->assertTrue(
                $this->verifyEs256($jwt, $publicKey),
                'La firma ES256 del token de APNs no valida contra su clave pública.',
            );

            [$header, $claims] = $this->decodeJwt($jwt);
            $this->assertSame('ES256', $header['alg']);
            $this->assertSame('ABC1234567', $header['kid']);
            $this->assertSame('TEAM123456', $claims['iss']);

            $body = json_decode($request->body(), true);
            $this->assertSame('Nuevo mensaje de WhatsApp', $body['aps']['alert']['title']);
            $this->assertSame('¿Tienen stock?', $body['aps']['alert']['body']);
            // Los datos van planos y como strings, que es lo que exige APNs.
            $this->assertSame('42', $body['conversation_id']);

            return true;
        });
    }

    public function test_apns_borra_el_dispositivo_cuando_apple_dice_que_ya_no_existe(): void
    {
        $this->configureApns();
        $user = $this->userWithDevice(DeviceToken::PLATFORM_IOS, 'token-muerto');

        // 410 Gone: la app se desinstaló. Seguir insistiendo solo gasta cuota.
        Http::fake(['api.sandbox.push.apple.com/*' => Http::response(['reason' => 'Unregistered'], 410)]);

        (new ApnsChannel)->send($user, new PushMessage('t', 'b'));

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_apns_conserva_el_dispositivo_ante_un_error_transitorio(): void
    {
        $this->configureApns();
        $user = $this->userWithDevice(DeviceToken::PLATFORM_IOS, 'token-vivo');

        // Un 503 es de Apple, no del aparato: el token sigue siendo válido.
        Http::fake(['api.sandbox.push.apple.com/*' => Http::response('', 503)]);

        (new ApnsChannel)->send($user, new PushMessage('t', 'b'));

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_apns_sin_credenciales_no_hace_nada(): void
    {
        config(['push.apns.key_id' => null, 'push.apns.private_key' => null, 'push.apns.private_key_path' => null]);
        $user = $this->userWithDevice(DeviceToken::PLATFORM_IOS, 'token');

        Http::fake();

        $channel = new ApnsChannel;
        $this->assertFalse($channel->isConfigured());

        $channel->send($user, new PushMessage('t', 'b'));

        Http::assertNothingSent();
    }

    public function test_fcm_canjea_la_service_account_por_un_access_token_y_envia(): void
    {
        [$privateKey, $publicKey] = $this->generateKey(OPENSSL_KEYTYPE_RSA);

        config([
            'push.fcm.credentials' => json_encode([
                'client_email' => 'push@cloud-api-cc.iam.gserviceaccount.com',
                'private_key' => $privateKey,
                'project_id' => 'cloud-api-cc',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
            'push.fcm.credentials_path' => null,
            'push.fcm.project_id' => null,
            'push.fcm.channel_id' => 'chats',
        ]);

        $user = $this->userWithDevice(DeviceToken::PLATFORM_ANDROID, 'fcm-device-token');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/cloud-api-cc/messages/1']),
        ]);

        (new FcmChannel)->send($user, new PushMessage(
            'Nuevo mensaje de WhatsApp',
            'Hola',
            ['event' => 'new_chat', 'conversation_id' => 7],
        ));

        // 1) El assertion RS256 con el que se pide el access token.
        Http::assertSent(function (Request $request) use ($publicKey) {
            if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
                return false;
            }

            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
            $this->assertSame(1, openssl_verify(
                implode('.', array_slice(explode('.', $request['assertion']), 0, 2)),
                $this->base64urlDecode(explode('.', $request['assertion'])[2]),
                $publicKey,
                OPENSSL_ALGO_SHA256,
            ), 'La firma RS256 del assertion de FCM no valida.');

            [, $claims] = $this->decodeJwt($request['assertion']);
            $this->assertSame('push@cloud-api-cc.iam.gserviceaccount.com', $claims['iss']);
            $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $claims['scope']);

            return true;
        });

        // 2) El envío en sí, con el project_id sacado del propio JSON.
        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $this->assertSame(
                'https://fcm.googleapis.com/v1/projects/cloud-api-cc/messages:send',
                $request->url(),
            );
            $this->assertSame('Bearer ya29.fake', $request->header('Authorization')[0]);
            $this->assertSame('fcm-device-token', $request['message']['token']);
            $this->assertArrayNotHasKey('notification', $request['message']);
            $this->assertSame('Nuevo mensaje de WhatsApp', $request['message']['data']['title']);
            $this->assertSame('Hola', $request['message']['data']['body']);
            $this->assertSame('new_chat', $request['message']['data']['event']);
            $this->assertSame('high', $request['message']['android']['priority']);
            $this->assertSame('conversation_7', $request['message']['android']['collapse_key']);
            $this->assertSame('3600s', $request['message']['android']['ttl']);
            $this->assertSame('7', $request['message']['data']['conversation_id']);

            return true;
        });
    }

    public function test_fcm_borra_el_dispositivo_cuando_el_token_ya_no_existe(): void
    {
        $this->configureFcm();
        $user = $this->userWithDevice(DeviceToken::PLATFORM_ANDROID, 'token-muerto');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);

        (new FcmChannel)->send($user, new PushMessage('t', 'b'));

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_cada_canal_solo_toca_los_dispositivos_de_su_plataforma(): void
    {
        $this->configureApns();
        $user = $this->userWithDevice(DeviceToken::PLATFORM_ANDROID, 'solo-android');

        Http::fake();

        (new ApnsChannel)->send($user, new PushMessage('t', 'b'));

        Http::assertNothingSent();
        $this->assertDatabaseCount('device_tokens', 1);
    }

    // ── Ayudas ──────────────────────────────────────────────────────────────

    private function userWithDevice(string $platform, string $token): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $user->deviceTokens()->create([
            'platform' => $platform,
            'token' => $token,
            'token_hash' => DeviceToken::hashFor($token),
        ]);

        return $user;
    }

    private function configureApns(): void
    {
        [$privateKey] = $this->generateKey(OPENSSL_KEYTYPE_EC);

        config([
            'push.apns.key_id' => 'ABC1234567',
            'push.apns.team_id' => 'TEAM123456',
            'push.apns.bundle_id' => 'com.cloudapicc.panel',
            'push.apns.private_key' => $privateKey,
            'push.apns.private_key_path' => null,
            'push.apns.production' => false,
        ]);
    }

    private function configureFcm(): void
    {
        [$privateKey] = $this->generateKey(OPENSSL_KEYTYPE_RSA);

        config([
            'push.fcm.credentials' => json_encode([
                'client_email' => 'push@cloud-api-cc.iam.gserviceaccount.com',
                'private_key' => $privateKey,
                'project_id' => 'cloud-api-cc',
            ]),
            'push.fcm.credentials_path' => null,
            'push.fcm.project_id' => null,
        ]);
    }

    /**
     * @return array{0: string, 1: string} [privada PEM, pública PEM]
     */
    private function generateKey(int $type): array
    {
        $config = $type === OPENSSL_KEYTYPE_EC
            ? ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']
            : ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];

        $key = openssl_pkey_new($config);
        $this->assertNotFalse($key, 'No se pudo generar la clave de prueba con openssl.');

        openssl_pkey_export($key, $private);

        return [$private, openssl_pkey_get_details($key)['key']];
    }

    /**
     * Verifica una firma ES256 de JWS: se vuelve a armar el DER que espera
     * openssl a partir del R||S crudo de 64 bytes. Es el camino inverso al que
     * hace Jwt::derToRaw(), así que valida esa conversión de punta a punta.
     */
    private function verifyEs256(string $jwt, string $publicKey): bool
    {
        [$header, $claims, $signature] = explode('.', $jwt);
        $raw = $this->base64urlDecode($signature);

        if (strlen($raw) !== 64) {
            return false;
        }

        $der = '';

        foreach ([substr($raw, 0, 32), substr($raw, 32)] as $part) {
            $part = ltrim($part, "\x00");

            // Si el primer bit está en 1, DER lo leería como negativo: se le
            // antepone un 0x00, que es justo lo que derToRaw() saca.
            if (ord($part[0]) > 0x7F) {
                $part = "\x00".$part;
            }

            $der .= "\x02".chr(strlen($part)).$part;
        }

        $der = "\x30".chr(strlen($der)).$der;

        return openssl_verify("{$header}.{$claims}", $der, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function decodeJwt(string $jwt): array
    {
        [$header, $claims] = explode('.', $jwt);

        return [
            json_decode($this->base64urlDecode($header), true),
            json_decode($this->base64urlDecode($claims), true),
        ];
    }

    private function base64urlDecode(string $value): string
    {
        return (string) base64_decode(str_pad(strtr($value, '-_', '+/'), (int) (ceil(strlen($value) / 4) * 4), '='));
    }
}
