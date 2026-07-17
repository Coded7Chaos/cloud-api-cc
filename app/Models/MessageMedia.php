<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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
     * URL firmada y temporal para descargar el archivo privado.
     * No expone la ruta del disco: apunta a la ruta media.download,
     * protegida por auth + firma, y caduca a los minutos indicados.
     */
    public function url(int $minutes = 5): string
    {
        return URL::temporarySignedRoute(
            'media.download',
            now()->addMinutes($minutes),
            ['media' => $this->id],
        );
    }
}
