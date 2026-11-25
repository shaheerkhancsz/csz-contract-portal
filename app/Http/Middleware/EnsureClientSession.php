<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the client area. A valid session is created only by the WHMCS SSO handoff
 * (ClientSsoController). This is a separate session key from the staff auth guard,
 * so a client session never grants access to the Filament panel.
 */
class EnsureClientSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session()->get('client_portal');

        if (! is_array($session) || empty($session['client_id']) || ($session['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('client_portal');

            return redirect()->route('client.expired');
        }

        // Sliding idle timeout: extend on activity.
        $request->session()->put('client_portal.expires_at', now()->addMinutes((int) config('portal.client_session_minutes'))->timestamp);

        return $next($request);
    }
}
