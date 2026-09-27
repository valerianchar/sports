<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
        });

        /*
         * Une ligne par exercice de la séance, dans l'ordre. L'exercice est un
         * slug du catalogue (database/data/exercises.php), qui vit dans le code.
         * Le mode est recopié : on peut chronométrer un exercice qui se compte
         * d'ordinaire en répétitions.
         */
        Schema::create('workout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('exercise', 64);
            $table->string('mode', 8);
            // Répétitions, ou secondes d'effort selon le mode.
            $table->unsignedSmallInteger('value');
            $table->unsignedTinyInteger('sets');
            $table->unsignedSmallInteger('rest_sets');
            $table->unsignedSmallInteger('rest_after');

            $table->index(['workout_id', 'position']);
        });

        /*
         * Une séance menée à son terme. Le nom est recopié : l'historique garde son
         * sens après qu'on a renommé ou supprimé la séance.
         */
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_id')->nullable()->constrained()->nullOnDelete();
            // Fabriqué par le téléphone : un envoi rejoué après une coupure réseau
            // ne compte pas la séance deux fois.
            $table->uuid('client_id')->unique();
            $table->string('name', 80);
            $table->unsignedInteger('duration_seconds');
            $table->unsignedSmallInteger('sets_done');
            $table->unsignedSmallInteger('exercises_done');
            $table->timestamp('finished_at');
            $table->timestamps();

            $table->index(['user_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_logs');
        Schema::dropIfExists('workout_items');
        Schema::dropIfExists('workouts');
    }
};
