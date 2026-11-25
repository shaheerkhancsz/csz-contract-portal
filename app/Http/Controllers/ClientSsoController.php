<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Services\Whmcs\ClientSso;
use App\Services\Whmcs\ClientSsoException;
use Illuminate\Http\Request;

/** Entry point for the WHMCS SSO handoff into the client area. */
class ClientSsoController extends Controller
{
    public function enter(Request $request, ClientSso $sso)
    {
        abort_unless($sso->configured(), 404);

        try {
            $payload = $sso->verify((string) $request->query('token', ''));
        } catch (ClientSsoException $e) {
            return response()->view('client.error', ['message' => $e->getMessage()], 403);
        }

        $ttl = (int) config('portal.sso_token_ttl_seconds', 120);
        if (! $sso->consumeNonce((string) $payload['nonce'], $ttl)) {
            return response()->view('client.error', [
                'message' => 'This sign-in link has already been used. Please open the client area from WHMCS again.',
            ], 403);
        }

        $client = Client::where('whmcs_client_id', (int) $payload['cid'])->first();
        if (! $client) {
            return response()->view('client.error', [
                'message' => 'We could not find your account in our system yet. Please contact us so we can link it.',
            ], 404);
        }

        // Fresh session id on sign-in, then store the client session (separate from staff auth).
        $request->session()->regenerate();
        $request->session()->put('client_portal', [
            'client_id' => $client->id,
            'name' => $client->company_name,
            'expires_at' => now()->addMinutes((int) config('portal.client_session_minutes'))->timestamp,
        ]);
        ActivityLog::record('client_signin', $client, 'Client signed in from WHMCS');

        return redirect()->route('client.contracts');
    }
}
