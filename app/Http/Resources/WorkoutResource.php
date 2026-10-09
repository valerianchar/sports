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
                'per_side' => $item->per_side,
                'value' => $item->value,
                'weight' => $item->weight,
                'set_weights' => $item->set_weights,
                'drops' => $item->drops,
                'drop_on' => $item->drop_on,
                'speed' => $item->speed,
                'incline' => $item->incline,
                'level' => $item->level,
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
                'feeling' => url('/journal/__client__/ressenti'),
                'weight' => route('workouts.weight.update', $this->resource),
                'settings' => route('workouts.settings.update', $this->resource),
            ],
        ];
    }
}
