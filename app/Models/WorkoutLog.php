<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workout_id', 'client_id', 'name', 'duration_seconds', 'sets_done', 'exercises_done', 'rpe', 'finished_at'])]
class WorkoutLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
            'duration_seconds' => 'integer',
            'sets_done' => 'integer',
            'exercises_done' => 'integer',
            'rpe' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SetLog, $this> */
    public function sets(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }

    /** @return BelongsTo<Workout, $this> */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
