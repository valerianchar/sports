<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Les mensurations, en centimètres : une ligne par jour au plus, comme
         * les pesées ; chaque mesure est facultative.
         */
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('waist', 5, 1)->nullable();
            $table->decimal('hips', 5, 1)->nullable();
            $table->decimal('chest', 5, 1)->nullable();
            $table->decimal('arm', 5, 1)->nullable();
            $table->decimal('thigh', 5, 1)->nullable();
            $table->decimal('calf', 5, 1)->nullable();
            $table->decimal('neck', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'measured_on']);
        });

        /*
         * Les photos de progression : le fichier est sur le disque privé, sous
         * photos/{user_id}/ ; seul son chemin est en base.
         */
        Schema::create('body_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->string('path');
            $table->string('pose', 10);
            $table->string('note', 140)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_photos');
        Schema::dropIfExists('body_measurements');
    }
};
