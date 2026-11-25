<?php

namespace App\Console\Commands;

use App\Services\Whmcs\ClientSso;
use Illuminate\Console\Command;

/**
 * Prints a signed client-area SSO URL for a WHMCS client id + email.
 *
 * For testing the handoff locally and for documenting the exact token algorithm the
 * WHMCS-side hook must reproduce. The WHMCS hook builds the same token in PHP:
 *
 *   $payload = json_encode(['cid'=>$clientId,'email'=>$email,'iat'=>time(),'exp'=>time()+120,'nonce'=>bin2hex(random_bytes(12))]);
 *   $b = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
 *   $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $b, WHMCS_SSO_SECRET, true)), '+/', '-_'), '=');
 *   $url = 'https://contracts.creativesolutionzone.com/client/sso?token=' . urlencode($b.'.'.$sig);
 */
class WhmcsSsoUrl extends Command
{
    protected $signature = 'whmcs:sso-url {whmcs_client_id} {email} {--ttl= : Seconds the link stays valid}';

    protected $description = 'Generate a signed client-area SSO URL (for testing the WHMCS handoff)';

    public function handle(ClientSso $sso): int
    {
        if (! $sso->configured()) {
            $this->error('WHMCS_SSO_SECRET is not set. Add it to .env first.');

            return self::FAILURE;
        }

        $ttl = $this->option('ttl') !== null ? (int) $this->option('ttl') : null;
        $url = $sso->makeUrl((int) $this->argument('whmcs_client_id'), (string) $this->argument('email'), $ttl);

        $this->line($url);

        return self::SUCCESS;
    }
}
