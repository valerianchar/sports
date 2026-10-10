<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BodyMeasurementController;
use App\Http\Controllers\BodyPhotoController;
use App\Http\Controllers\BodyWeightController;
use App\Http\Controllers\CustomSoundController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ExerciseEquivalentController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MuscleZoneController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\PushAlertController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\WorkoutAssistantController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutItemSettingsController;
use App\Http\Controllers\WorkoutItemWeightController;
use App\Http\Controllers\WorkoutLogController;
use App\Http\Controllers\WorkoutShareController;
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
    // Variantes d'un exercice, pour le changer dans l'éditeur — JSON.
    // Avant la route paramétrée : « zones » n'est pas un exercice.
    Route::get('/exercices/zones/{zone}', [MuscleZoneController::class, 'show'])->name('exercises.zones');
    Route::get('/exercices/{exercise}/equivalents', [ExerciseEquivalentController::class, 'index'])->name('exercises.equivalents');

    // Avant la route paramétrée : « nouvelle » et « assistant » ne sont pas des identifiants de séance.
    Route::get('/seances/nouvelle', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::get('/seances/assistant', [WorkoutAssistantController::class, 'create'])->name('workouts.assistant');
    Route::get('/seances/assistant/proposition', [WorkoutAssistantController::class, 'suggest'])->name('workouts.assistant.suggest');
    // Compléter une séance en cours d'édition — JSON.
    Route::post('/seances/assistant/completer', [WorkoutAssistantController::class, 'complete'])
        ->middleware('throttle:60,1')->name('workouts.assistant.complete');
    Route::get('/seances', [WorkoutController::class, 'list'])->name('workouts.list');
    Route::post('/seances', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::get('/seances/{workout}/modifier', [WorkoutController::class, 'edit'])->name('workouts.edit');
    Route::put('/seances/{workout}', [WorkoutController::class, 'update'])->name('workouts.update');
    Route::delete('/seances/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
    Route::get('/seances/{workout}/lancer', [WorkoutController::class, 'play'])->name('workouts.play');
    Route::post('/seances/{workout}/dupliquer', [WorkoutShareController::class, 'duplicate'])->name('workouts.duplicate');
    // Partage : le lien (JSON), puis la séance partagée et son import.
    Route::post('/seances/{workout}/partage', [WorkoutShareController::class, 'link'])->middleware('throttle:30,1')->name('workouts.share');
    Route::post('/seances/{workout}/partage/mail', [WorkoutShareController::class, 'mail'])->middleware('throttle:10,60')->name('workouts.share.mail');
    Route::get('/partage/{token}', [WorkoutShareController::class, 'show'])->name('shares.show');
    Route::post('/partage/{token}', [WorkoutShareController::class, 'import'])->middleware('throttle:30,1')->name('shares.import');

    // Charge changée depuis le lecteur — JSON.
    Route::patch('/seances/{workout}/charge', [WorkoutItemWeightController::class, 'update'])
        ->middleware('throttle:60,1')->name('workouts.weight.update');
    // Réglages d'une machine de cardio changés depuis le lecteur — JSON.
    Route::patch('/seances/{workout}/reglages', [WorkoutItemSettingsController::class, 'update'])
        ->middleware('throttle:60,1')->name('workouts.settings.update');

    // Journal d'une séance terminée — JSON, rejouable.
    Route::post('/seances/{workout}/journal', [WorkoutLogController::class, 'store'])
        ->middleware('throttle:30,1')->name('workouts.logs.store');

    Route::patch('/journal/{clientId}/ressenti', [WorkoutLogController::class, 'feeling'])
        ->whereUuid('clientId')->middleware('throttle:30,1')->name('workouts.logs.feeling');

    // Notifications : abonnement du téléphone, alertes d'une séance en arrière-plan — JSON.
    Route::post('/notifications/abonnement', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:20,1')->name('push.subscribe');
    Route::delete('/notifications/abonnement', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::post('/notifications/essai', [PushSubscriptionController::class, 'test'])
        ->middleware('throttle:5,1')->name('push.test');
    Route::put('/seances/alertes/{session}', [PushAlertController::class, 'store'])
        ->whereUuid('session')->middleware('throttle:120,1')->name('push.alerts.store');
    Route::delete('/seances/alertes/{session}', [PushAlertController::class, 'destroy'])
        ->whereUuid('session')->middleware('throttle:120,1')->name('push.alerts.destroy');

    // Progrès : statistiques de performance, pesées.
    Route::get('/progres', [ProgressController::class, 'index'])->name('progress.index');
    Route::get('/progres/exercices/{exercise}', [ProgressController::class, 'exercise'])->name('progress.exercise');
    Route::post('/progres/poids', [BodyWeightController::class, 'store'])->name('body-weights.store');
    Route::patch('/progres/objectif-poids', [BodyWeightController::class, 'target'])->name('body-weights.target');
    Route::delete('/progres/poids/{bodyWeight}', [BodyWeightController::class, 'destroy'])->name('body-weights.destroy');

    // Journal des séances faites : la liste, le détail série par série.
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::get('/journal/{log}', [JournalController::class, 'show'])->whereNumber('log')->name('journal.show');
    Route::delete('/journal/{log}', [JournalController::class, 'destroy'])->whereNumber('log')->name('journal.destroy');
    Route::post('/progres/mensurations', [BodyMeasurementController::class, 'store'])->name('body-measurements.store');
    Route::delete('/progres/mensurations/{measurement}', [BodyMeasurementController::class, 'destroy'])->name('body-measurements.destroy');
    // Photos de progression : disque privé, servies au seul propriétaire.
    Route::post('/progres/photos', [BodyPhotoController::class, 'store'])->middleware('throttle:20,1')->name('body-photos.store');
    Route::get('/progres/photos/{photo}', [BodyPhotoController::class, 'show'])->name('body-photos.show');
    Route::delete('/progres/photos/{photo}', [BodyPhotoController::class, 'destroy'])->name('body-photos.destroy');

    Route::get('/programme', [ScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('/programme', [ScheduleController::class, 'update'])->name('schedule.update');
    Route::get('/reglages', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::put('/reglages', [PreferencesController::class, 'update'])->name('preferences.update');
    Route::post('/reglages/son', [CustomSoundController::class, 'store'])->middleware('throttle:10,1')->name('preferences.sound.store');
    Route::get('/reglages/son', [CustomSoundController::class, 'show'])->name('preferences.sound.show');
    Route::delete('/reglages/son', [CustomSoundController::class, 'destroy'])->name('preferences.sound.destroy');
});
