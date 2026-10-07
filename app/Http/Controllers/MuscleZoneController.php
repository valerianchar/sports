<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentKind;
use App\Support\ExerciseCatalog;
use App\Support\MuscleZones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Des exercices pour une zone de muscle que la séance ne touche pas encore —
 * le bas des pectoraux, l'arrière des épaules… JSON, pour l'éditeur et
 * l'assistant.
 */
class MuscleZoneController extends Controller
{
    public function show(Request $request, string $zone): JsonResponse
    {
        abort_unless(MuscleZones::exists($zone), 404);

        $data = $request->validate([
            'exclude' => ['array', 'max:'.config('sport.max_items')],
            'exclude.*' => ['string', Rule::in(ExerciseCatalog::slugs())],
            'equipment' => ['nullable', Rule::enum(EquipmentKind::class)->only([EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight])],
        ]);

        $slugs = MuscleZones::suggest($zone, $data['exclude'] ?? [], EquipmentKind::tryFrom((string) ($data['equipment'] ?? '')));
        $exercises = collect(ExerciseCatalog::forClient($slugs))->keyBy('slug');

        return response()->json([
            'exercises' => array_values(array_map(fn (string $slug): array => $exercises[$slug], $slugs)),
        ]);
    }
}
