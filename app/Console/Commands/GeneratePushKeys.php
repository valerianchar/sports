<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Produit une paire de clés VAPID à placer dans le .env : VAPID_PUBLIC_KEY,
 * VAPID_PRIVATE_KEY. À faire une fois par installation.
 */
class GeneratePushKeys extends Command
{
    protected $signature = 'alertes:cles';

    protected $description = 'Génère une paire de clés VAPID pour les notifications web';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");

        return self::SUCCESS;
    }
}
