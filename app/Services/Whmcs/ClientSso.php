<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Signed SSO handoff from WHMCS into the client area.
 *
 * Token = base64url(payload JSON) . "." . base64url(HMAC-SHA256(payload, sso_secret)).
 * Payload: { cid: whmcs_client_id, email, iat, exp, nonce }. Short-lived and single-use.
 *
 * The WHMCS side builds the same token with the shared secret (services.whmcs.sso_secret);
 * `php artisan whmcs:sso-url` prints one for testing and documents the exact algorithm.
 */
class ClientSso
{
    public function configured(): bool
    {
        return filled(config('services.whmcs.sso_secret'));
    }

    /** Build a signed token (and full URL) for a WHMCS client id + email. */
    public function makeToken(int $whmcsClientId, string $email, ?int $ttl = null): string
    {
        $now = now()->getTimestamp();
        $payload = [
            'cid' => $whmcsClientId,
            'email' => $email,
            'iat' => $now,
            'exp' => $now + ($ttl ?? (int) config('portal.sso_token_ttl_seconds', 120)),
            'nonce' => Str::random(24),
        ];
        $body = $this->b64(json_encode($payload));

        return $body.'.'.$this->b64($this->sign($body));
    }

    public function makeUrl(int $whmcsClientId, string $email, ?int $ttl = null): string
    {
        return url('/client/sso').'?token='.urlencode($this->makeToken($whmcsClientId, $email, $ttl));
    }

    /**
     * Verify signature and expiry. Returns the payload array, or throws ClientSsoException.
     * Replay (single-use) is enforced separately via consumeNonce().
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new ClientSsoException('Malformed sign-in link.');
        }
        [$body, $sig] = $parts;

        $expected = $this->b64($this->sign($body));
        if (! hash_equals($expected, $sig)) {
            throw new ClientSsoException('This sign-in link could not be verified.');
        }

        $payload = json_decode((string) $this->unb64($body), true);
        if (! is_array($payload) || ! isset($payload['cid'], $payload['exp'], $payload['nonce'])) {
            throw new ClientSsoException('This sign-in link is invalid.');
        }

        $now = now()->getTimestamp();
        if ((int) $payload['exp'] < $now) {
            throw new ClientSsoException('This sign-in link has expired. Please open the client area from WHMCS again.');
        }
        if (isset($payload['iat']) && (int) $payload['iat'] > $now + 60) {
            throw new ClientSsoException('This sign-in link is not valid yet.');
        }

        return $payload;
    }

    /** Mark a nonce used; returns false if it was already used (replay). */
    public function consumeNonce(string $nonce, int $ttlSeconds): bool
    {
        // Cache::add is atomic: true only if the key did not already exist.
        return Cache::add('whmcs_sso_nonce:'.$nonce, true, now()->addSeconds($ttlSeconds + 60));
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('services.whmcs.sso_secret'), true);
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function unb64(string $b64): string
    {
        return (string) base64_decode(strtr($b64, '-_', '+/'), true);
    }
}
