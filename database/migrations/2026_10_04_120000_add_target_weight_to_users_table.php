<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Le poids visé, pour qui veut maigrir (ou prendre de la masse) : facultatif.
            $table->decimal('target_weight', 5, 2)->nullable()->after('custom_sound_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('target_weight');
        });
    }
};
