<?php

namespace App\Models;

use Database\Factories\WorkoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name'])]
class Workout extends Model
{
    /** @use HasFactory<WorkoutFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WorkoutItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WorkoutItem::class)->orderBy('position');
    }

    /** @return HasMany<WorkoutLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkoutLog::class);
    }

    /** @return HasOne<WorkoutLog, $this> */
    public function latestLog(): HasOne
    {
        // « Faite il y a… » : la dernière fois qu'elle a été menée au bout.
        return $this->hasOne(WorkoutLog::class)->ofMany(['finished_at' => 'max'], fn ($query) => $query->where('completed', true));
    }
}
