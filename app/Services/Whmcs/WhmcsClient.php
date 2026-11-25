<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the WHMCS External API. Phase 2 uses it only to look up a client
 * (by email or WHMCS ID) so staff can link a portal client to their WHMCS account.
 */
class WhmcsClient
{
    /** True only when URL + API credentials are all set. */
    public function configured(): bool
    {
        $cfg = config('services.whmcs');

        return filled($cfg['url'] ?? null) && filled($cfg['identifier'] ?? null) && filled($cfg['secret'] ?? null);
    }

    public function findClientByEmail(string $email): ?WhmcsClientData
    {
        return $this->findClient(['email' => trim($email)]);
    }

    public function findClientById(int $id): ?WhmcsClientData
    {
        return $this->findClient(['clientid' => $id]);
    }

    /** Deep link into the WHMCS admin client summary, when admin_url is configured. */
    public function adminClientUrl(int $id): ?string
    {
        $base = config('services.whmcs.admin_url');

        return $base ? rtrim($base, '/').'/clientssummary.php?userid='.$id : null;
    }

    private function findClient(array $params): ?WhmcsClientData
    {
        $data = $this->call('GetClientsDetails', $params + ['stats' => false]);

        if (strtolower((string) ($data['result'] ?? '')) === 'error') {
            $message = (string) ($data['message'] ?? 'WHMCS error');
            if (str_contains(strtolower($message), 'not found')) {
                return null;   // no such client — not an error for our purposes
            }
            throw new WhmcsException($message);
        }

        return WhmcsClientData::fromResponse($data['client'] ?? $data);
    }

    /** @return array<string,mixed> decoded JSON response */
    private function call(string $action, array $params): array
    {
        $cfg = config('services.whmcs');

        try {
            $response = Http::asForm()
                ->timeout((int) ($cfg['timeout'] ?? 10))
                ->post(rtrim((string) $cfg['url'], '/').'/includes/api.php', array_merge($params, [
                    'identifier' => $cfg['identifier'],
                    'secret' => $cfg['secret'],
                    'action' => $action,
                    'responsetype' => 'json',
                ]));
        } catch (\Throwable) {
            // Never surface the secret or raw exception; keep the message actionable.
            throw new WhmcsException('Could not reach WHMCS. Check the URL and that this server can connect to it.');
        }

        if ($response->failed()) {
            throw new WhmcsException('WHMCS returned an error (HTTP '.$response->status().'). Check the API credentials and IP allowlist.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new WhmcsException('WHMCS returned an unexpected response.');
        }

        return $data;
    }
}
