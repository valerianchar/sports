<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'exercise', 'position', 'set_number', 'drop', 'reps', 'target_reps', 'seconds', 'weight', 'e1rm', 'volume', 'performed_at'])]
class SetLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'position' => 'integer',
            'set_number' => 'integer',
            'drop' => 'integer',
            'reps' => 'integer',
            'target_reps' => 'integer',
            'seconds' => 'integer',
            'weight' => 'float',
            'e1rm' => 'float',
            'volume' => 'float',
        ];
    }

    /** @return BelongsTo<WorkoutLog, $this> */
    public function workoutLog(): BelongsTo
    {
        return $this->belongsTo(WorkoutLog::class);
    }
}
