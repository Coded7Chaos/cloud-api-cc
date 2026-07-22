<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'message_id', 'disk', 'storage_path', 'mime_type',
    'original_filename', 'size', 'sha256', 'wa_media_id',
])]
class MessageMedia extends Model
{
    protected $table = 'message_media';

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * URL para descargar el archivo privado. No expone la ruta del disco:
     * apunta a media.show, que autoriza por conversación (ver MediaController).
     *
     * Es estable a propósito, no firmada-y-temporal: las apps móviles cachean
     * las imágenes por URL, y un link que caduca a los minutos tira ese caché
     * a la basura en cada refresco del hilo. El SPA la pide con su cookie de
     * sesión y el móvil con su token Bearer.
     */
    public function url(): string
    {
        return route('media.show', ['media' => $this->id]);
    }
}
