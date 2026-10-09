<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            // Exercice d'un côté puis de l'autre, ou en alternant : la valeur
            // (répétitions, secondes) vaut-elle pour chaque côté ? Vide : sans objet.
            $table->boolean('per_side')->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('workout_items', fn (Blueprint $table) => $table->dropColumn('per_side'));
    }
};
