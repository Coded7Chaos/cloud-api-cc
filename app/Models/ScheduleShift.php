<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_version_id', 'weekday', 'start_time', 'end_time'])]
class ScheduleShift extends Model
{
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'schedule_version_id');
    }
}
