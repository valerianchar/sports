<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deux façons de faire varier la charge :
     * - par série (dégressif, pyramide) : une charge par série, dans
     *   `set_weights` — `weight` reste la charge fixe quand il n'y en a pas ;
     * - en drop set : des paliers {reps, weight} enchaînés sans repos après la
     *   série, sur la dernière série ou sur chacune (`drop_on`).
     */
    public function up(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            $table->json('set_weights')->nullable()->after('weight');
            $table->json('drops')->nullable()->after('set_weights');
            $table->string('drop_on', 4)->nullable()->after('drops');
        });
    }

    public function down(): void
    {
        Schema::table('workout_items', function (Blueprint $table) {
            $table->dropColumn(['set_weights', 'drops', 'drop_on']);
        });
    }
};
