<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class WebPushGenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-vapid';

    protected $description = 'Genera claves VAPID para notificaciones Web Push';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
