<?php

namespace App\Models;

use App\Enums\ExerciseMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'exercise', 'mode', 'value', 'weight', 'set_weights', 'drops', 'drop_on', 'sets', 'rest_sets', 'rest_after'])]
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
            'weight' => 'float',
            'set_weights' => 'array',
            'drops' => 'array',
            'sets' => 'integer',
            'rest_sets' => 'integer',
            'rest_after' => 'integer',
        ];
    }

    /** @return BelongsTo<Workout, $this> */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
