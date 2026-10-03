<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le compte à rebours sonore avant la fin d'un repos ou d'une série
     * chronométrée — combien de secondes, et à quel volume les bips : dans une
     * salle bruyante, 30 % ne s'entendent pas.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('countdown_seconds')->default(5)->after('prep_seconds');
            $table->unsignedTinyInteger('volume')->default(80)->after('countdown_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['countdown_seconds', 'volume']);
        });
    }
};
