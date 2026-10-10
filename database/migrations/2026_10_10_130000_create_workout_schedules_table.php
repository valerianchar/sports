<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le programme de la semaine : une séance prévue par jour, à une heure, avec ou sans rappel.
        Schema::create('workout_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 1 = lundi … 7 = dimanche (ISO).
            $table->unsignedTinyInteger('weekday');
            $table->foreignId('workout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('time', 5)->nullable();
            $table->boolean('remind')->default(false);
            // Le jour du dernier rappel envoyé : un seul par jour.
            $table->date('reminded_on')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'weekday']);
        });

        Schema::table('users', function (Blueprint $table) {
            // L'objectif de séances par semaine (0 : pas d'objectif).
            $table->unsignedTinyInteger('weekly_goal')->default(0)->after('warmup_sets');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('weekly_goal'));
        Schema::dropIfExists('workout_schedules');
    }
};
