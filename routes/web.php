<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\WorkoutController;
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

    // Avant la route paramétrée : « nouvelle » n'est pas un identifiant de séance.
    Route::get('/seances/nouvelle', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::post('/seances', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::get('/seances/{workout}/modifier', [WorkoutController::class, 'edit'])->name('workouts.edit');
    Route::put('/seances/{workout}', [WorkoutController::class, 'update'])->name('workouts.update');
    Route::delete('/seances/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
    Route::get('/seances/{workout}/lancer', [WorkoutController::class, 'play'])->name('workouts.play');

    // Journal d'une séance terminée — JSON, rejouable.
    Route::post('/seances/{workout}/journal', [WorkoutLogController::class, 'store'])
        ->middleware('throttle:30,1')->name('workouts.logs.store');

    Route::put('/reglages', [PreferencesController::class, 'update'])->name('preferences.update');
});
