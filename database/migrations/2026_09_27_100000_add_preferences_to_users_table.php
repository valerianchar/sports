<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les réglages du lecteur suivent le compte, pas le téléphone : on retrouve
     * les mêmes bips et le même compte à rebours sur la tablette de la salle.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sound')->default(true)->after('password');
            $table->unsignedTinyInteger('prep_seconds')->default(5)->after('sound');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sound', 'prep_seconds']);
        });
    }
};
