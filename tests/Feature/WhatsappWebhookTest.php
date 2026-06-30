<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

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
}
