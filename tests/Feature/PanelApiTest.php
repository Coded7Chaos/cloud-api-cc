<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_flow_end_to_end(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::where('email', 'agente@cc.test')->firstOrFail();

        // Sin sesión, la API protegida responde 401 (no redirige).
        $this->getJson('/api/conversations')->assertUnauthorized();

        $this->actingAs($agent);

        // Bandeja de chats.
        $list = $this->getJson('/api/conversations')->assertOk()->json('data');
        $this->assertNotEmpty($list);
        $conversationId = $list[0]['id'];

        // Detalle del hilo con sus mensajes.
        $this->getJson("/api/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'contact' => ['wa_id'], 'messages']]);

        // Falseamos a Meta con una SECUENCIA: el 1er envío sale OK, el 2do falla.
        // (Con dos Http::fake() separados, Laravel acumula los stubs y gana el
        //  primero, así que se usa fakeSequence para controlar el orden.)
        Http::fakeSequence('graph.facebook.com/*')
            ->push(['messages' => [['id' => 'wamid.FAKE123']]], 200)
            ->push(['error' => ['message' => 'Business account locked']], 400);

        // 1) Envío exitoso: guarda el wamid real y queda como "sent".
        $sent = $this->postJson("/api/conversations/{$conversationId}/messages", [
            'body' => 'Hola desde el test',
        ])->assertCreated()->json('data');

        $this->assertSame('sent', $sent['status']);
        $this->assertDatabaseHas('messages', [
            'wa_message_id' => 'wamid.FAKE123',
            'direction' => 'outbound',
            'sender_user_id' => $agent->id,
        ]);

        // 2) Si Meta falla, el mensaje queda "failed" pero igual se persiste.
        $failed = $this->postJson("/api/conversations/{$conversationId}/messages", [
            'body' => 'Este debería fallar',
        ])->assertCreated()->json('data');
        $this->assertSame('failed', $failed['status']);

        // Usuarios y horarios.
        $this->getJson('/api/users')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/schedules')->assertOk()->assertJsonStructure(['data']);

        // Actualizar el horario del agente (reemplazo total).
        $this->putJson("/api/users/{$agent->id}/schedule", [
            'shifts' => [
                ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '17:00'],
                ['weekday' => 2, 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('schedule_shifts', ['weekday' => 1, 'start_time' => '08:00']);
    }
}
