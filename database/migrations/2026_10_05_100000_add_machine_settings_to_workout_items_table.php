<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            // Les réglages d'une machine de cardio : vitesse (km/h), inclinaison (%), niveau de résistance.
            $table->decimal('speed', 4, 1)->nullable()->after('drop_on');
            $table->decimal('incline', 4, 1)->nullable()->after('speed');
            $table->unsignedTinyInteger('level')->nullable()->after('incline');
        });
    }

    public function down(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            $table->dropColumn(['speed', 'incline', 'level']);
        });
    }
};
