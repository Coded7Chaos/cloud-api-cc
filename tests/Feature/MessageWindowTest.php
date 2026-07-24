<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageWindowTest extends TestCase
{
    use RefreshDatabase;

    private function conversationWithLastInboundAt(\DateTimeInterface $sentAt): Conversation
    {
        $contact = Contact::create(['wa_id' => '5215500000000', 'profile_name' => 'Cliente de prueba']);
        $conversation = Conversation::create(['contact_id' => $contact->id, 'status' => 'open']);

        Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid-inbound-'.Str::uuid(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hola',
            'status' => 'delivered',
            'sent_at' => $sentAt,
        ]);

        return $conversation;
    }

    private function actingAsAgent(): User
    {
        $this->seed(RoleSeeder::class);
        $agent = User::factory()->soporte()->create();
        app(ScheduleService::class)->createSchedule($agent, [
            ['weekday' => now()->dayOfWeekIso, 'start_time' => '00:00', 'end_time' => '23:59'],
        ], now()->copy()->startOfMonth());
        $this->actingAs($agent);

        return $agent;
    }

    public function test_can_send_within_the_24_hour_window(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT1']]], 200)]);

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hola, ¿en qué te ayudo?'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'direction' => 'outbound']);
    }

    public function test_versioned_mobile_endpoint_returns_the_message_sent_to_whatsapp(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.MOBILE1']]], 200)]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Respuesta desde Android'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Respuesta desde Android')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('delivery_failed', false);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid.MOBILE1',
            'status' => 'sent',
        ]);
    }

    public function test_failed_whatsapp_delivery_is_visible_to_the_mobile_client(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token.'],
        ], 401)]);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Mensaje que Meta rechazará'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Mensaje que Meta rechazará')
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('delivery_failed', true)
            ->assertJsonPath('message', 'El mensaje quedó guardado, pero WhatsApp rechazó la entrega.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Mensaje que Meta rechazará',
            'status' => 'failed',
        ]);
    }

    public function test_cannot_send_after_the_24_hour_window_expires(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(25));

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Llego tarde'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, '24 horas'));

        // El guard corta antes de crear nada: sigue habiendo un solo mensaje (el entrante inicial).
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_window_reopens_after_a_new_inbound_message(): void
    {
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(30));

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Ya no debería poder'])
            ->assertStatus(422);

        // El cliente vuelve a escribir -> se reabre la ventana desde ese momento.
        Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid-inbound-'.Str::uuid(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Sigo ahí',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT2']]], 200)]);

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Ahora sí puedo'])
            ->assertCreated();
    }

    public function test_can_send_image_with_optional_caption(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_IMAGE_1'], 200)
            ->push(['messages' => [['id' => 'wamid.IMAGE1']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Mira esta imagen',
            'media' => UploadedFile::fake()->image('foto.jpg', 800, 600),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.IMAGE1',
            'direction' => 'outbound',
            'type' => 'image',
            'body' => 'Mira esta imagen',
        ]);
        $this->assertDatabaseHas('message_media', [
            'mime_type' => 'image/jpeg',
            'wa_media_id' => 'MEDIA_IMAGE_1',
        ]);
    }

    public function test_can_send_video_with_optional_caption(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_VIDEO_1'], 200)
            ->push(['messages' => [['id' => 'wamid.VIDEO1']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Mira este video',
            'media' => UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.VIDEO1',
            'direction' => 'outbound',
            'type' => 'video',
            'body' => 'Mira este video',
        ]);
        $this->assertDatabaseHas('message_media', [
            'mime_type' => 'video/mp4',
            'wa_media_id' => 'MEDIA_VIDEO_1',
        ]);
    }

    public function test_can_send_a_document_and_whatsapp_receives_its_filename(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_DOC_1'], 200)
            ->push(['messages' => [['id' => 'wamid.DOC1']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Te mando el comprobante',
            'media' => UploadedFile::fake()->create('comprobante.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'document')
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.DOC1',
            'direction' => 'outbound',
            'type' => 'document',
        ]);

        // Sin filename, al cliente le llega con un nombre autogenerado.
        Http::assertSent(function ($request) {
            $document = $request->data()['document'] ?? null;

            return $document
                && $document['filename'] === 'comprobante.pdf'
                && $document['caption'] === 'Te mando el comprobante';
        });
    }

    public function test_audio_travels_without_caption_because_whatsapp_rejects_it(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_AUDIO_1'], 200)
            ->push(['messages' => [['id' => 'wamid.AUDIO1']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'media' => UploadedFile::fake()->create('nota-de-voz.ogg', 64, 'audio/ogg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.status', 'sent');

        Http::assertSent(function ($request) {
            $audio = $request->data()['audio'] ?? null;

            return $audio === null || ! array_key_exists('caption', $audio);
        });
    }

    public function test_a_voice_note_in_an_mp4_container_is_sent_as_audio(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_VOICE_1'], 200)
            ->push(['messages' => [['id' => 'wamid.VOICE1']]], 200);

        // Las notas de voz de la app móvil son .m4a, y el contenedor MP4 hace
        // que se detecten como video/mp4: sin desempatar por extensión, se
        // enviarían como mensaje de video.
        $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'media' => UploadedFile::fake()->create('nota-de-voz.m4a', 128, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'audio');

        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.VOICE1',
            'type' => 'audio',
        ]);
        // El mime guardado también se corrige, para que el reproductor del
        // panel y del móvil la traten como audio y no como video.
        $this->assertDatabaseHas('message_media', ['mime_type' => 'audio/mp4']);

        Http::assertSent(function ($request) {
            $audio = $request->data()['audio'] ?? null;

            return $audio === null || ! array_key_exists('caption', $audio);
        });
    }

    public function test_an_audio_cannot_carry_a_caption(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Escuchá esto',
            'media' => UploadedFile::fake()->create('nota-de-voz.ogg', 64, 'audio/ogg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseMissing('messages', ['direction' => 'outbound']);
    }

    public function test_the_size_limit_depends_on_the_media_type(): void
    {
        Storage::fake('local');
        $this->actingAsAgent();
        $conversation = $this->conversationWithLastInboundAt(now()->subHours(2));

        // 6 MB pasa el tope de imagen (5 MB) pero está lejos del de documento.
        $this->post("/api/conversations/{$conversation->id}/messages", [
            'media' => UploadedFile::fake()->create('grande.jpg', 6 * 1024, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('media');

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'MEDIA_DOC_2'], 200)
            ->push(['messages' => [['id' => 'wamid.DOC2']]], 200);

        $this->post("/api/conversations/{$conversation->id}/messages", [
            'media' => UploadedFile::fake()->create('grande.pdf', 6 * 1024, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'document');
    }
}
