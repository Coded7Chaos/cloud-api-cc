<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageMedia;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Acceso a los adjuntos de los chats.
 *
 * La URL dejó de ser firmada-y-temporal para que el caché de imágenes del
 * móvil sirva de algo, así que la firma ya no hace de control de acceso: quien
 * autoriza ahora es la conversación. Estos tests cubren justamente eso, porque
 * el modo anterior dependía de que el id fuera imposible de adivinar.
 */
class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_agente_asignado_puede_descargar_el_adjunto(): void
    {
        [$owner, , $media] = $this->conversationWithMedia();

        $this->actingAs($owner)
            ->get("/api/media/{$media->id}")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_un_agente_ajeno_no_puede_descargar_el_adjunto_de_otro(): void
    {
        [, $otherAgent, $media] = $this->conversationWithMedia();

        // 404 y no 403: si respondiéramos 403, el id serviría para confirmar
        // que ese adjunto existe.
        $this->actingAs($otherAgent)
            ->get("/api/media/{$media->id}")
            ->assertNotFound();
    }

    public function test_el_administrador_puede_descargar_cualquier_adjunto(): void
    {
        [, , $media] = $this->conversationWithMedia();

        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'administrador')->value('id'),
        ]);

        $this->actingAs($admin)->get("/api/media/{$media->id}")->assertOk();
    }

    public function test_sin_autenticar_no_se_descarga_nada(): void
    {
        [, , $media] = $this->conversationWithMedia();

        $this->getJson("/api/media/{$media->id}")->assertUnauthorized();
    }

    public function test_la_url_del_adjunto_sirve_tal_cual_la_devuelve_la_api(): void
    {
        // El cliente no arma la URL: la toma de la respuesta. Si el nombre de
        // ruta o el prefijo cambian, esto se cae acá y no en producción.
        [$owner, , $media] = $this->conversationWithMedia();

        $url = $this->actingAs($owner)
            ->getJson("/api/conversations/{$media->message->conversation_id}")
            ->assertOk()
            ->json('data.messages.0.media.0.url');

        $this->actingAs($owner)->get($url)->assertOk();
    }

    /**
     * @return array{0: User, 1: User, 2: MessageMedia}
     */
    private function conversationWithMedia(): array
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $supportRoleId = Role::where('name', 'soporte')->value('id');
        $owner = User::factory()->create(['role_id' => $supportRoleId]);
        $otherAgent = User::factory()->create(['role_id' => $supportRoleId]);

        $contact = Contact::create(['wa_id' => '59170000099', 'profile_name' => 'Cliente']);
        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $owner->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'wamid.MEDIA',
            'direction' => 'inbound',
            'type' => 'image',
            'body' => null,
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        Storage::disk('local')->put('whatsapp/media/foto.jpg', 'BINARIO');

        $media = $message->media()->create([
            'disk' => 'local',
            'storage_path' => 'whatsapp/media/foto.jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => 'foto.jpg',
            'size' => 7,
        ]);

        return [$owner, $otherAgent, $media];
    }
}
