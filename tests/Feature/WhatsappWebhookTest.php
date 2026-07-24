<?php

namespace Tests\Feature;

use App\Jobs\SendAutomaticReply;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AutoReplyService;
use App\Services\PushNotificationService;
use App\Services\ScheduleService;
use App\Services\WhatsappService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendAutomaticReply::class]);
    }

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

    public function test_first_inbound_message_sends_an_automatic_reply(): void
    {
        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->withArgs(function (string $to, string $body) {
                    $this->assertSame('59170000005', $to);
                    $this->assertSame('Hola, recibimos tu mensaje. Un agente te atenderá en breve.', $body);

                    return true;
                })
                ->andReturn(['messages' => [['id' => 'wamid.AUTO_REPLY']]]);
        });

        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000005', 'wamid.AUTO_FIRST', 'Hola'))
            ->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000005'))->firstOrFail();
        $inbound = Message::where('wa_message_id', 'wamid.AUTO_FIRST')->firstOrFail();
        Queue::assertPushed(SendAutomaticReply::class);

        app(AutoReplyService::class)->handleInboundMessage($conversation, $inbound);

        $this->assertTrue((bool) $conversation->fresh()->auto_reply_sent);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => 'Hola, recibimos tu mensaje. Un agente te atenderá en breve.',
        ]);
    }

    public function test_webhook_only_queues_the_automatic_reply(): void
    {
        Queue::fake();

        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000055', 'wamid.AUTO_QUEUED', 'Hola'))
            ->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000055'))->firstOrFail();
        $message = Message::where('wa_message_id', 'wamid.AUTO_QUEUED')->firstOrFail();

        Queue::assertPushed(SendAutomaticReply::class);
        $this->assertFalse((bool) $conversation->auto_reply_sent);
        $this->assertSame($conversation->id, $message->conversation_id);
    }

    public function test_failed_auto_reply_is_not_marked_as_sent_and_can_be_retried(): void
    {
        $contact = Contact::create(['wa_id' => '59170000056', 'profile_name' => 'Cliente']);
        $conversation = $contact->conversations()->create(['status' => 'open']);
        $message = $conversation->messages()->create([
            'wa_message_id' => 'wamid.AUTO_RETRY_INBOUND',
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hola',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->andThrow(new \RuntimeException('Meta temporalmente no disponible'));
        });

        try {
            app(AutoReplyService::class)->handleInboundMessage($conversation, $message);
            $this->fail('El fallo de Meta debía propagarse para que la cola reintente.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Meta temporalmente no disponible', $e->getMessage());
        }

        $this->assertFalse((bool) $conversation->fresh()->auto_reply_sent);
        $this->assertSame(0, $conversation->messages()->where('direction', 'outbound')->count());

        $this->forgetMock(WhatsappService::class);
        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->andReturn(['messages' => [['id' => 'wamid.AUTO_RETRY_OK']]]);
        });

        $this->assertTrue(app(AutoReplyService::class)->handleInboundMessage($conversation, $message));
        $this->assertTrue((bool) $conversation->fresh()->auto_reply_sent);
        $this->assertDatabaseHas('messages', ['wa_message_id' => 'wamid.AUTO_RETRY_OK', 'status' => 'sent']);
    }

    public function test_same_conversation_does_not_receive_two_auto_replies(): void
    {
        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendText')
                ->andReturn(['messages' => [['id' => 'wamid.AUTO_REPLY']]]);
        });

        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000006', 'wamid.AUTO_DUP_1', 'Primero'))
            ->assertOk();
        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000006', 'wamid.AUTO_DUP_2', 'Segundo'))
            ->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000006'))->firstOrFail();
        foreach ($conversation->messages()->where('direction', 'inbound')->get() as $inbound) {
            app(AutoReplyService::class)->handleInboundMessage($conversation, $inbound);
        }

        $this->assertTrue((bool) $conversation->fresh()->auto_reply_sent);
        $this->assertSame(1, $conversation->messages()->where('direction', 'outbound')->where('body', 'Hola, recibimos tu mensaje. Un agente te atenderá en breve.')->count());
    }

    public function test_auto_reply_is_not_sent_for_agent_or_system_messages(): void
    {
        $this->seed(RoleSeeder::class);

        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendText');
        });

        $contact = Contact::create(['wa_id' => '59170000007', 'profile_name' => 'Cliente']);
        $conversation = $contact->conversations()->create(['status' => 'open']);
        $message = $conversation->messages()->create([
            'wa_message_id' => 'wamid.SYSTEM_OUTBOUND',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'No responder',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        app(AutoReplyService::class)->handleInboundMessage($conversation, $message);
    }

    public function test_auto_reply_is_not_sent_for_assigned_conversations(): void
    {
        $this->seed(RoleSeeder::class);

        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendText');
        });

        $agent = User::factory()->soporte()->create();
        $contact = Contact::create(['wa_id' => '59170000008', 'profile_name' => 'Cliente asignado']);
        $conversation = $contact->conversations()->create([
            'assigned_user_id' => $agent->id,
            'status' => 'open',
        ]);

        $message = $conversation->messages()->create([
            'wa_message_id' => 'wamid.ASSIGNED_INBOUND',
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hola',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        app(AutoReplyService::class)->handleInboundMessage($conversation, $message);
        $this->assertFalse((bool) $conversation->fresh()->auto_reply_sent);
    }

    public function test_auto_reply_does_not_create_a_loop_for_the_system_message(): void
    {
        $this->seed(RoleSeeder::class);

        $this->mock(WhatsappService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->andReturn(['messages' => [['id' => 'wamid.AUTO_REPLY_LOOP']]]);
        });

        $contact = Contact::create(['wa_id' => '59170000009', 'profile_name' => 'Cliente bucle']);
        $conversation = $contact->conversations()->create(['status' => 'open']);
        $inbound = $conversation->messages()->create([
            'wa_message_id' => 'wamid.LOOP_INBOUND',
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hola',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        app(AutoReplyService::class)->handleInboundMessage($conversation, $inbound);

        $systemMessage = $conversation->messages()->create([
            'wa_message_id' => 'wamid.LOOP_SYSTEM',
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Hola, recibimos tu mensaje. Un agente te atenderá en breve.',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        app(AutoReplyService::class)->handleInboundMessage($conversation, $systemMessage);
        $this->assertSame(2, $conversation->messages()->where('direction', 'outbound')->where('body', 'Hola, recibimos tu mensaje. Un agente te atenderá en breve.')->count());
    }

    public function test_new_inbound_chat_notifies_every_support_agent_in_working_hours_without_assigning_it(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 13, 10, 0));
        $this->seed(RoleSeeder::class);
        $workingA = User::factory()->soporte()->create();
        $workingB = User::factory()->soporte()->create();
        $outsideShift = User::factory()->soporte()->create();

        foreach ([$workingA, $workingB] as $agent) {
            app(ScheduleService::class)->createSchedule($agent, [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
            ], Carbon::create(2026, 7, 1));
        }
        app(ScheduleService::class)->createSchedule($outsideShift, [
            ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '22:00'],
        ], Carbon::create(2026, 7, 1));

        $this->mock(PushNotificationService::class, function (MockInterface $mock) use ($workingA, $workingB) {
            $expectedIds = collect([$workingA->id, $workingB->id])->sort()->values()->all();
            $notifiedIds = [];

            $mock->shouldReceive('sendToUser')
                ->twice()
                ->withArgs(function (User $user, string $title, string $body, array $data) use (&$notifiedIds) {
                    $notifiedIds[] = $user->id;

                    return $title === 'Nuevo chat de WhatsApp'
                        && $body === 'Hola'
                        && $data['event'] === 'new_chat'
                        && $data['conversation_id'] > 0;
                })
                ->andReturnUsing(function () use (&$notifiedIds, $expectedIds) {
                    if (count($notifiedIds) === 2) {
                        $this->assertSame($expectedIds, collect($notifiedIds)->sort()->values()->all());
                    }
                });
        });

        $this->postJson('/api/whatsapp/webhook', $this->inboundTextPayload('59170000020', 'wamid.ASSIGN', 'Hola'))
            ->assertOk();

        $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('wa_id', '59170000020'))->firstOrFail();
        $this->assertNull($conversation->assigned_user_id);
        $this->assertDatabaseHas('conversation_notification_recipients', [
            'conversation_id' => $conversation->id,
            'user_id' => $workingA->id,
        ]);
        $this->assertDatabaseHas('conversation_notification_recipients', [
            'conversation_id' => $conversation->id,
            'user_id' => $workingB->id,
        ]);
        $this->assertDatabaseMissing('conversation_notification_recipients', [
            'conversation_id' => $conversation->id,
            'user_id' => $outsideShift->id,
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

        // La vieja conserva exactamente su mensaje entrante.
        $this->assertSame(1, $oldConversation->messages()->count());
        $this->assertSame('Hola en junio', $oldConversation->messages()->where('wa_message_id', 'wamid.OLD')->firstOrFail()->body);
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
}
