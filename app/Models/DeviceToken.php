<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un teléfono registrado para recibir notificaciones nativas.
 *
 * @property string $platform ios | android
 */
#[Fillable([
    'user_id', 'personal_access_token_id', 'platform', 'token',
    'token_hash', 'app_version', 'device_name', 'last_used_at',
])]
class DeviceToken extends Model
{
    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public static function hashFor(string $token): string
    {
        return hash('sha256', $token);
    }
}
