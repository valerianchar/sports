<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le son du compte à rebours : un son intégré (bip par défaut), ou un
     * fichier envoyé par l'utilisateur, rangé sur le disque privé.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('countdown_sound', 16)->default('bip')->after('volume');
            $table->string('custom_sound_path')->nullable()->after('countdown_sound');
            $table->string('custom_sound_name', 120)->nullable()->after('custom_sound_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['countdown_sound', 'custom_sound_path', 'custom_sound_name']);
        });
    }
};
