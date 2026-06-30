<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'effective_from', 'effective_to', 'reason'])]
class ScheduleVersion extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ScheduleShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(ScheduleShift::class);
    }

    /** La versión vigente todavía no tiene fecha de corte. */
    public function isOpen(): bool
    {
        return $this->effective_to === null;
    }
}
