<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Services\ScheduleService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'last_name', 'email', 'password', 'role_id', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * URL de la foto de perfil, calculada. Se agrega a la serialización para
     * que el SPA la reciba junto al resto del usuario (en /user y /profile).
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * URL al endpoint que sirve la foto (no la ruta del archivo, que es
     * privada). Relativa a propósito: el SPA es del mismo origen, así funciona
     * en cualquier dominio sin depender de APP_URL. El ?v= cambia con cada
     * guardado para romper el caché del navegador al subir una foto nueva.
     * null si no tiene foto.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? route('profile.avatar', [], false).'?v='.($this->updated_at?->timestamp ?? 0)
            : null);
    }

    /** @return HasMany<ScheduleVersion, $this> */
    public function scheduleVersions(): HasMany
    {
        return $this->hasMany(ScheduleVersion::class);
    }

    /** @return BelongsToMany<Tarea, $this> */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'tarea_user')
            ->withPivot(['status', 'completed_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Conversation, $this> */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    /** @return HasMany<Message, $this> */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id');
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Dispositivos móviles (APNs/FCM) registrados por este usuario. */
    /** @return HasMany<DeviceToken, $this> */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $name): bool
    {
        return $this->role?->name === $name;
    }

    public function hasPermission(string $name): bool
    {
        return $this->role?->permissions->contains('name', $name) ?? false;
    }

    public function canAccessPlatformAt(?Carbon $at = null): bool
    {
        return app(ScheduleService::class)->canAccessPlatformAt($this, $at ?? now());
    }

    public function canReceiveWorkNotificationsAt(?Carbon $at = null): bool
    {
        return app(ScheduleService::class)->canReceiveWorkNotificationsAt($this, $at ?? now());
    }

    public function canReceiveNewChatsAt(?Carbon $at = null): bool
    {
        return app(ScheduleService::class)->canReceiveNewChatsAt($this, $at ?? now());
    }

    /** Usa nuestra notificación en español (link al SPA) en vez de la de Laravel. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
