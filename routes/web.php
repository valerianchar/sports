<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BodyWeightController;
use App\Http\Controllers\CustomSoundController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\WorkoutAssistantController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutItemWeightController;
use App\Http\Controllers\WorkoutLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store']);

    Route::middleware('registration')->group(function () {
        Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    });

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/nouveau-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/nouveau-mot-de-passe', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [WorkoutController::class, 'index'])->name('workouts.index');
    Route::get('/exercices', [ExerciseController::class, 'index'])->name('exercises.index');

    // Avant la route paramétrée : « nouvelle » et « assistant » ne sont pas des identifiants de séance.
    Route::get('/seances/nouvelle', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::get('/seances/assistant', [WorkoutAssistantController::class, 'create'])->name('workouts.assistant');
    Route::get('/seances/assistant/proposition', [WorkoutAssistantController::class, 'suggest'])->name('workouts.assistant.suggest');
    Route::post('/seances', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::get('/seances/{workout}/modifier', [WorkoutController::class, 'edit'])->name('workouts.edit');
    Route::put('/seances/{workout}', [WorkoutController::class, 'update'])->name('workouts.update');
    Route::delete('/seances/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
    Route::get('/seances/{workout}/lancer', [WorkoutController::class, 'play'])->name('workouts.play');

    // Charge changée depuis le lecteur — JSON.
    Route::patch('/seances/{workout}/charge', [WorkoutItemWeightController::class, 'update'])
        ->middleware('throttle:60,1')->name('workouts.weight.update');

    // Journal d'une séance terminée — JSON, rejouable.
    Route::post('/seances/{workout}/journal', [WorkoutLogController::class, 'store'])
        ->middleware('throttle:30,1')->name('workouts.logs.store');

    Route::patch('/journal/{clientId}/ressenti', [WorkoutLogController::class, 'feeling'])
        ->whereUuid('clientId')->middleware('throttle:30,1')->name('workouts.logs.feeling');

    // Progrès : statistiques de performance, pesées.
    Route::get('/progres', [ProgressController::class, 'index'])->name('progress.index');
    Route::get('/progres/exercices/{exercise}', [ProgressController::class, 'exercise'])->name('progress.exercise');
    Route::post('/progres/poids', [BodyWeightController::class, 'store'])->name('body-weights.store');
    Route::delete('/progres/poids/{bodyWeight}', [BodyWeightController::class, 'destroy'])->name('body-weights.destroy');

    Route::put('/reglages', [PreferencesController::class, 'update'])->name('preferences.update');
    Route::post('/reglages/son', [CustomSoundController::class, 'store'])->middleware('throttle:10,1')->name('preferences.sound.store');
    Route::get('/reglages/son', [CustomSoundController::class, 'show'])->name('preferences.sound.show');
    Route::delete('/reglages/son', [CustomSoundController::class, 'destroy'])->name('preferences.sound.destroy');
});
