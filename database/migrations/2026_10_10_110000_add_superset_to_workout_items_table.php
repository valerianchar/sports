<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            // Enchaîné avec l'exercice suivant, sans repos : un superset (deux) ou un circuit (plus).
            $table->boolean('superset')->default(false)->after('rest_after');
        });
    }

    public function down(): void
    {
        Schema::table('workout_items', fn (Blueprint $table) => $table->dropColumn('superset'));
    }
};
