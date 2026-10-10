<?php

namespace App\Models;

use App\Enums\ExerciseMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'exercise', 'mode', 'per_side', 'value', 'weight', 'set_weights', 'drops', 'drop_on', 'speed', 'incline', 'level', 'sets', 'rest_sets', 'rest_after', 'superset'])]
class WorkoutItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ExerciseMode::class,
            'position' => 'integer',
            'value' => 'integer',
            'per_side' => 'boolean',
            'weight' => 'float',
            'set_weights' => 'array',
            'drops' => 'array',
            'speed' => 'float',
            'incline' => 'float',
            'level' => 'integer',
            'sets' => 'integer',
            'rest_sets' => 'integer',
            'rest_after' => 'integer',
            'superset' => 'boolean',
        ];
    }

    /** @return BelongsTo<Workout, $this> */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
