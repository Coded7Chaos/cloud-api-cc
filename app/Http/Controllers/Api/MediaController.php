<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Sirve un adjunto guardado en el disco privado.
     *
     * La URL es estable (antes era firmada y caducaba a los 5 minutos): así el
     * caché de imágenes del móvil sirve de algo y no hay que re-pedir el hilo
     * para refrescar links vencidos. A cambio, la firma ya no hace de control
     * de acceso, así que la autorización es explícita: el archivo solo se
     * entrega si su conversación es visible para quien lo pide.
     *
     * La ruta del disco nunca se expone: el front usa $media->url().
     */
    public function show(Request $request, MessageMedia $media): StreamedResponse
    {
        $media->loadMissing('message');

        // 404 y no 403 a propósito: quien no puede ver el chat tampoco debería
        // poder deducir que ese adjunto existe.
        abort_unless(
            Conversation::query()
                ->whereKey($media->message?->conversation_id)
                ->visibleTo($request->user())
                ->exists(),
            404,
        );

        abort_unless(Storage::disk($media->disk)->exists($media->storage_path), 404);

        return Storage::disk($media->disk)->response(
            $media->storage_path,
            $media->original_filename,
            ['Content-Type' => $media->mime_type ?? 'application/octet-stream'],
        );
    }
}
