<?php

namespace App\Models;

use App\Enums\PhotoPose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une photo de progression. Le fichier vit sur le disque privé (`local`) et
 * n'est servi qu'à son propriétaire, par BodyPhotoController::show.
 */
#[Fillable(['taken_on', 'path', 'pose', 'note'])]
#[Hidden(['path'])]
class BodyPhoto extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_on' => 'date',
            'pose' => PhotoPose::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
