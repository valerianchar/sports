<?php

namespace App\Models;

use App\Enums\AudioMode;
use App\Enums\CountdownSound;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'sound', 'prep_seconds', 'countdown_seconds', 'volume', 'countdown_sound', 'audio_mode', 'warmup_sets', 'weekly_goal', 'custom_sound_path', 'custom_sound_name', 'target_weight'])]
#[Hidden(['password', 'remember_token', 'custom_sound_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Les réglages par défaut, les mêmes qu'en base : un compte tout juste créé
     * les connaît sans être relu.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sound' => true,
        'prep_seconds' => 5,
        'countdown_seconds' => 5,
        'volume' => 80,
        'countdown_sound' => 'bip',
        'audio_mode' => 'melange',
        'warmup_sets' => true,
        'weekly_goal' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sound' => 'boolean',
            'prep_seconds' => 'integer',
            'countdown_seconds' => 'integer',
            'volume' => 'integer',
            'countdown_sound' => CountdownSound::class,
            'audio_mode' => AudioMode::class,
            'warmup_sets' => 'boolean',
            'weekly_goal' => 'integer',
            'target_weight' => 'float',
        ];
    }

    /** @return HasMany<WorkoutSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(WorkoutSchedule::class)->orderBy('weekday');
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<PushAlert, $this> */
    public function pushAlerts(): HasMany
    {
        return $this->hasMany(PushAlert::class);
    }

    /** @return HasMany<Workout, $this> */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /** @return HasMany<SetLog, $this> */
    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }

    /** @return HasMany<BodyWeight, $this> */
    public function bodyWeights(): HasMany
    {
        return $this->hasMany(BodyWeight::class);
    }

    /** @return HasMany<BodyMeasurement, $this> */
    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    /** @return HasMany<BodyPhoto, $this> */
    public function bodyPhotos(): HasMany
    {
        return $this->hasMany(BodyPhoto::class);
    }

    /** @return HasMany<WorkoutLog, $this> */
    public function workoutLogs(): HasMany
    {
        return $this->hasMany(WorkoutLog::class);
    }

    /**
     * Prénom seul pour les salutations (« Salut Marie »).
     */
    protected function firstName(): Attribute
    {
        return Attribute::get(fn (): string => Str::of($this->name)->squish()->before(' ')->value());
    }

    /**
     * Initiales affichées dans la pastille du compte.
     */
    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode(''));
    }

    /**
     * Adresse du son personnel, s'il y en a un — l'horodatage dans l'adresse
     * fait oublier au navigateur l'ancien fichier quand on le remplace.
     */
    protected function customSoundUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->custom_sound_path === null
            ? null
            : route('preferences.sound.show', ['v' => $this->updated_at?->timestamp]));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
