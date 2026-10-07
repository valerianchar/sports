<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les téléphones qui acceptent les notifications : un abonnement par appareil.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // L'adresse d'abonnement est longue : son empreinte sert de clé unique.
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestamps();
        });

        // Les alertes d'une séance en cours quand l'appli passe en arrière-plan :
        // fin d'un repos, reprise de l'effort. Envoyées à l'heure dite, puis oubliées.
        Schema::create('push_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session');
            $table->dateTime('send_at', 3);
            $table->string('title', 120);
            $table->string('body', 240)->nullable();
            $table->dateTime('sent_at', 3)->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'send_at']);
            $table->index(['user_id', 'session']);
        });

        Schema::table('users', function (Blueprint $table) {
            // « melange » : les bips se glissent dans la musique ; « prioritaire » :
            // ils sonnent même en mode silencieux, mais coupent la musique.
            $table->string('audio_mode', 20)->default('melange')->after('countdown_sound');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('audio_mode'));
        Schema::dropIfExists('push_alerts');
        Schema::dropIfExists('push_subscriptions');
    }
};
