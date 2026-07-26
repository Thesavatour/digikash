<?php

namespace App\Console\Commands;

use App\Services\WebPush\WebPushSender;
use Illuminate\Console\Command;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'webpush:vapid {--show : Print keys without writing .env}';

    protected $description = 'Generate VAPID key pair for OS-level Web Push notifications';

    public function handle(WebPushSender $sender): int
    {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            $this->error('OpenSSL failed to create an EC key.');

            return self::FAILURE;
        }

        $details = openssl_pkey_get_details($key);
        $x       = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y       = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $d       = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);
        $public  = $sender->base64UrlEncode("\x04".$x.$y);
        $private = $sender->base64UrlEncode($d);

        $this->line('VAPID_PUBLIC_KEY='.$public);
        $this->line('VAPID_PRIVATE_KEY='.$private);
        $this->line('VAPID_SUBJECT="mailto:support@'.parse_url((string) config('app.url'), PHP_URL_HOST).'"');

        if ($this->option('show')) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Add the lines above to your .env file (keep them stable once devices are subscribed).');

        return self::SUCCESS;
    }
}
