<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une séance interrompue en route ne dit rien des performances : seules
     * les séances menées au bout — toutes les séries prévues faites — nourrissent
     * les statistiques. Les journaux antérieurs, sans le détail des séries, ne
     * peuvent pas le prouver : ils restent hors du compte.
     */
    public function up(): void
    {
        Schema::table('workout_logs', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->after('exercises_done');
            $table->unsignedSmallInteger('planned_sets')->nullable()->after('completed');
            $table->index(['user_id', 'completed', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'completed', 'finished_at']);
            $table->dropColumn(['completed', 'planned_sets']);
        });
    }
};
