<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'échauffement se choisit exercice par exercice, désactivé par défaut.
        Schema::table('workout_items', function (Blueprint $table) {
            $table->boolean('warmup')->default(false)->after('superset');
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('warmup_sets'));

        // Gauche / droite aussi, désactivé par défaut : les côtés posés d'office jusqu'ici s'effacent.
        DB::table('workout_items')->update(['per_side' => null]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('warmup_sets')->default(true)->after('audio_mode'));
        Schema::table('workout_items', fn (Blueprint $table) => $table->dropColumn('warmup'));
    }
};
