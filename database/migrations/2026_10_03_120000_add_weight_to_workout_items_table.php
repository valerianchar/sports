<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La charge d'un exercice, en kilos. Vide : au poids du corps, ou pas
     * encore renseignée. Au quart de kilo près, pour les micro-disques.
     */
    public function up(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            $table->decimal('weight', 6, 2)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
