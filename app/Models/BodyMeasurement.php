<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Les mensurations d'un jour, en centimètres : chaque colonne est facultative,
 * on ne mesure pas forcément tout à chaque fois.
 */
#[Fillable(['measured_on', 'waist', 'hips', 'chest', 'arm', 'thigh', 'calf', 'neck'])]
class BodyMeasurement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'waist' => 'float',
            'hips' => 'float',
            'chest' => 'float',
            'arm' => 'float',
            'thigh' => 'float',
            'calf' => 'float',
            'neck' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
