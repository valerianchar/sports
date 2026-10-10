<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            // Le lien de partage d'une séance : créé à la demande, unique, impossible à deviner.
            $table->string('share_token', 32)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', fn (Blueprint $table) => $table->dropColumn('share_token'));
    }
};
