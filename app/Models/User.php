<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Services\ScheduleService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'last_name', 'email', 'password', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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

    /** @return HasMany<ScheduleVersion, $this> */
    public function scheduleVersions(): HasMany
    {
        return $this->hasMany(ScheduleVersion::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
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
