<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['titulo', 'descripcion'])]
class Tarea extends Model
{
    protected $table = 'tareas';

    /** @return BelongsToMany<User, $this> */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tarea_user')
            ->withPivot(['status', 'completed_at'])
            ->withTimestamps();
    }
}
