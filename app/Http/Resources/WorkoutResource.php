<?php

namespace App\Http\Resources;

use App\Models\Workout;
use App\Models\WorkoutItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workout
 */
class WorkoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'items' => $this->items->map(fn (WorkoutItem $item): array => [
                'exercise' => $item->exercise,
                'mode' => $item->mode->value,
                'value' => $item->value,
                'sets' => $item->sets,
                'rest_sets' => $item->rest_sets,
                'rest_after' => $item->rest_after,
            ])->values(),
            'last_done' => $this->whenLoaded('latestLog', fn (): ?string => $this->latestLog?->finished_at->diffForHumans()),
            'urls' => [
                'edit' => route('workouts.edit', $this->resource),
                'update' => route('workouts.update', $this->resource),
                'destroy' => route('workouts.destroy', $this->resource),
                'play' => route('workouts.play', $this->resource),
                'log' => route('workouts.logs.store', $this->resource),
            ],
        ];
    }
}
