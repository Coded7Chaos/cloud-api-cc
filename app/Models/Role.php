<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Role extends Model
{
    /**
     * Roles cableados en la lógica de la app: el nombre "administrador" abre el
     * panel de administración y su dashboard, y "soporte" es el que se asigna
     * por defecto a los usuarios nuevos (UserController::store). Renombrarlos o
     * borrarlos rompería esas suposiciones, así que la gestión de roles los
     * protege: se les pueden editar los permisos, pero no el nombre ni la baja.
     */
    public const PROTECTED = ['administrador', 'soporte'];

    public function isProtected(): bool
    {
        return in_array($this->name, self::PROTECTED, true);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
