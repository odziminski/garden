<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateWebPushKeys extends Command
{
    protected $signature = 'push:generate-keys';

    protected $description = 'Generate a VAPID key pair for browser care reminders';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable) {
            $this->error('OpenSSL could not generate keys. Check the PHP OpenSSL configuration and elliptic curve support.');

            return self::FAILURE;
        }

        $this->line('WEB_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEB_PUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
