<?php

namespace App\Http\Controllers;

use App\Models\MessageMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Sirve un archivo guardado en el disco privado.
     *
     * Protegido por auth + firma (URL temporal). La ruta del disco nunca
     * se expone: el front usa $media->url(), que es una URL firmada y caduca.
     */
    public function show(MessageMedia $media): StreamedResponse
    {
        abort_unless(Storage::disk($media->disk)->exists($media->storage_path), 404);

        return Storage::disk($media->disk)->response(
            $media->storage_path,
            $media->original_filename,
            ['Content-Type' => $media->mime_type ?? 'application/octet-stream'],
        );
    }
}
