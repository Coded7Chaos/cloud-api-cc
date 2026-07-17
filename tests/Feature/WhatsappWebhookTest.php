<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Payload de un mensaje entrante de texto, tal como lo manda Meta. */
    private function inboundTextPayload(string $waId, string $wamid, string $body): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'contacts' => [['profile' => ['name' => 'María López'], 'wa_id' => $waId]],
                        'messages' => [[
                            'from' => $waId,
                            'id' => $wamid,
                            'timestamp' => '1782757600',
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /** Payload de una media entrante de WhatsApp, con caption opcional. */
    private function inboundMediaPayload(
        string $waId,
        string $wamid,
        string $type,
        string $mediaId,
        ?string $caption = null,
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'contacts' => [['profile' => ['name' => 'Media User'], 'wa_id' => $waId]],
                        'messages' => [[
                            'from' => $waId,
                            'id' => $wamid,
                            'timestamp' => '1782757600',
                            'type' => $type,
                            $type => array_filter([
                                'id' => $mediaId,
                                'caption' => $caption,
                            ]),
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function test_verification_returns_challenge_with_valid_token(): void
    {
        Config::set('services.whatsapp.verify_token', 'secreto');

        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=secreto&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_verification_is_rejected_with_invalid_token(): void
    {
        Config::set('services.whatsapp.verify_token', 'secreto');

        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=malo&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_inbound_message_creates_contact_conversation_and_message(): void
    {
        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000001', 'wamid.A1', 'Hola, una consulta'))
            ->assertOk();

        $this->assertDatabaseHas('contacts', ['wa_id' => '59170000001', 'profile_name' => 'María López']);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.A1',
            'direction' => 'inbound',
            'body' => 'Hola, una consulta',
        ]);
    }

    public function test_new_inbound_chat_is_assigned_to_support_agent_in_working_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 10, 0));
        $this->seed(RoleSeeder::class);
        $agent = User::factory()->soporte()->create();
        app(ScheduleService::class)->createSchedule($agent, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ], Carbon::create(2026, 7, 1));

        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000020', 'wamid.ASSIGN', 'Hola'))
            ->assertOk();

        $this->assertDatabaseHas('conversations', [
            'assigned_user_id' => $agent->id,
        ]);
    }

    public function test_new_inbound_chat_is_not_assigned_to_agents_outside_working_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 18, 0));
        $this->seed(RoleSeeder::class);
        $agent = User::factory()->soporte()->create();
        app(ScheduleService::class)->createSchedule($agent, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ], Carbon::create(2026, 7, 1));

        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000021', 'wamid.NOASSIGN', 'Hola'))
            ->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000021'))->firstOrFail();
        $this->assertNull($conversation->assigned_user_id);
    }

    public function test_message_after_the_24h_window_closed_starts_a_new_conversation(): void
    {
        // Primer mensaje: abre la conversación A.
        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000010', 'wamid.OLD', 'Hola en junio'))
            ->assertOk();

        $oldConversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000010'))->firstOrFail();

        // Simulamos que pasó tiempo real: su único mensaje queda a más de 24h.
        $oldConversation->messages()->update(['sent_at' => now()->subDays(20)]);
        $oldConversation->update(['last_message_at' => now()->subDays(20)]);

        // El mismo contacto vuelve a escribir mucho después: tiene que ser
        // una conversación NUEVA, no la reapertura de la anterior.
        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000010', 'wamid.NEW', 'Hola de nuevo en julio'))
            ->assertOk();

        $this->assertDatabaseCount('conversations', 2);
        $this->assertDatabaseCount('messages', 2);

        $newMessage = Message::where('wa_message_id', 'wamid.NEW')->firstOrFail();
        $this->assertNotSame($oldConversation->id, $newMessage->conversation_id);

        // La vieja queda intacta: sigue con un solo mensaje, tal como estaba.
        $this->assertSame(1, $oldConversation->messages()->count());
    }

    public function test_redelivered_webhook_after_the_window_closed_still_does_not_duplicate_or_move_the_message(): void
    {
        $payload = $this->inboundTextPayload('59170000011', 'wamid.REDELIVER', 'Mensaje original');
        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000011'))->firstOrFail();

        // Pasa el tiempo (la ventana de esa conversación cierra) y Meta reentrega
        // el MISMO evento (wamid repetido): no debe crear una 2da conversación
        // ni mover el mensaje, porque ya se había procesado la primera vez.
        $conversation->messages()->update(['sent_at' => now()->subDays(5)]);
        $conversation->update(['last_message_at' => now()->subDays(5)]);

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertSame(1, Message::where('wa_message_id', 'wamid.REDELIVER')->count());
        $this->assertSame($conversation->id, Message::where('wa_message_id', 'wamid.REDELIVER')->value('conversation_id'));
    }

    public function test_duplicate_webhook_does_not_duplicate_message(): void
    {
        $payload = $this->inboundTextPayload('59170000002', 'wamid.DUP', 'Mensaje repetido');

        // Meta reentrega el mismo webhook: debe quedar UNA sola fila.
        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();
        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertSame(1, Message::where('wa_message_id', 'wamid.DUP')->count());
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_status_webhook_updates_outbound_message(): void
    {
        $contact = Contact::create(['wa_id' => '59170000003', 'profile_name' => 'Cliente']);
        $conversation = $contact->conversations()->create(['status' => 'open']);
        $message = $conversation->messages()->create([
            'wa_message_id' => 'wamid.OUT1',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Respuesta',
            'status' => 'sent',
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.OUT1',
                            'status' => 'read',
                            'timestamp' => '1782757700',
                            'recipient_id' => '59170000003',
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertSame('read', $message->fresh()->status);
    }

    public function test_inbound_media_is_downloaded_to_storage(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            // 1) metadata de la media (URL temporal + datos)
            'graph.facebook.com/*' => Http::response([
                'url' => 'https://media.test/file.jpg',
                'mime_type' => 'image/jpeg',
                'sha256' => 'deadbeef',
                'file_size' => 2048,
            ], 200),
            // 2) descarga del binario
            'media.test/*' => Http::response('CONTENIDO-BINARIO', 200),
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [['profile' => ['name' => 'Foto User'], 'wa_id' => '59170000004']],
                        'messages' => [[
                            'from' => '59170000004',
                            'id' => 'wamid.IMG1',
                            'timestamp' => '1782757600',
                            'type' => 'image',
                            'image' => ['id' => 'MEDIA123', 'mime_type' => 'image/jpeg', 'caption' => 'mira esto'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $message = Message::where('wa_message_id', 'wamid.IMG1')->firstOrFail();
        $this->assertSame('image', $message->type);
        $this->assertSame('mira esto', $message->body); // el caption va al body

        $media = $message->media()->firstOrFail();
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('MEDIA123', $media->wa_media_id);
        Storage::disk('local')->assertExists($media->storage_path);
    }

    public function test_inbound_image_is_returned_with_media_url_in_conversation_detail(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'url' => 'https://media.test/image.jpg',
                'mime_type' => 'image/jpeg',
                'sha256' => 'image-hash',
                'file_size' => 2048,
            ], 200),
            'media.test/*' => Http::response('IMAGE-BINARY', 200),
        ]);

        $this->postJson('/api/whatsapp/webhook', $this->inboundMediaPayload(
            '59170000030',
            'wamid.IMG_DETAIL',
            'image',
            'MEDIA_IMG_DETAIL',
            'foto entrante',
        ))->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000030'))->firstOrFail();

        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());

        $this->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.type', 'image')
            ->assertJsonPath('data.messages.0.body', 'foto entrante')
            ->assertJsonPath('data.messages.0.media.0.mime_type', 'image/jpeg')
            ->assertJsonPath('data.messages.0.media.0.original_filename', null)
            ->assertJsonPath('data.messages.0.media.0.size', 2048)
            ->assertJson(fn ($json) => $json
                ->where('data.messages.0.media.0.id', fn ($id) => is_int($id))
                ->where('data.messages.0.media.0.url', fn ($url) => is_string($url) && str_contains($url, '/media/'))
                ->etc());
    }

    public function test_inbound_video_is_downloaded_and_returned_with_media_url(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'url' => 'https://media.test/video.mp4',
                'mime_type' => 'video/mp4',
                'sha256' => 'video-hash',
                'file_size' => 4096,
            ], 200),
            'media.test/*' => Http::response('VIDEO-BINARY', 200),
        ]);

        $this->postJson('/api/whatsapp/webhook', $this->inboundMediaPayload(
            '59170000031',
            'wamid.VID_DETAIL',
            'video',
            'MEDIA_VID_DETAIL',
            'video entrante',
        ))->assertOk();

        $message = Message::where('wa_message_id', 'wamid.VID_DETAIL')->firstOrFail();
        $this->assertSame('video', $message->type);
        $this->assertSame('video entrante', $message->body);

        $media = $message->media()->firstOrFail();
        $this->assertSame('video/mp4', $media->mime_type);
        $this->assertSame('MEDIA_VID_DETAIL', $media->wa_media_id);
        Storage::disk('local')->assertExists($media->storage_path);

        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->administrador()->create());

        $this->getJson("/api/conversations/{$message->conversation_id}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.type', 'video')
            ->assertJsonPath('data.messages.0.media.0.mime_type', 'video/mp4')
            ->assertJson(fn ($json) => $json
                ->where('data.messages.0.media.0.url', fn ($url) => is_string($url) && str_contains($url, '/media/'))
                ->etc());
    }
}
