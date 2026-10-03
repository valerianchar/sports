<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Chaque série réellement faite : la matière première de tous les
         * indicateurs de progression. L'exercice est un slug du catalogue ; le 1RM
         * estimé et le volume sont calculés à l'écriture pour que les
         * statistiques n'aient qu'à lire.
         */
        Schema::create('set_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exercise', 64);
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('set_number');
            // Palier de drop set (dès 0) ; vide pour une série normale.
            $table->unsignedTinyInteger('drop')->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedSmallInteger('target_reps')->nullable();
            $table->unsignedSmallInteger('seconds')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('e1rm', 7, 2)->nullable();
            $table->decimal('volume', 9, 2)->default(0);
            $table->timestamp('performed_at');

            $table->index(['user_id', 'performed_at']);
            $table->index(['user_id', 'exercise', 'performed_at']);
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            // Difficulté ressentie, de 1 (facile) à 10 (maximale).
            $table->unsignedTinyInteger('rpe')->nullable()->after('exercises_done');
        });

        Schema::create('body_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('kg', 5, 2);
            $table->date('measured_on');
            $table->timestamps();

            $table->unique(['user_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_weights');
        Schema::table('workout_logs', fn (Blueprint $table) => $table->dropColumn('rpe'));
        Schema::dropIfExists('set_logs');
    }
};
