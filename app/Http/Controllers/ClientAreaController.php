<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** The WHMCS-SSO-gated client area: list your contracts, download signed copies, jump to signing. */
class ClientAreaController extends Controller
{
    public function index(Request $request)
    {
        $client = $this->client($request);

        // Only contracts that have left internal review are visible to the client.
        $contracts = $client->contracts()
            ->whereNotIn('status', [ContractStatus::Draft->value, ContractStatus::AwaitingApproval->value])
            ->with(['documents', 'signingRequests'])
            ->latest('id')->get();

        return view('client.contracts', ['client' => $client, 'contracts' => $contracts]);
    }

    public function pdf(Request $request, Contract $contract)
    {
        $client = $this->client($request);
        abort_unless($contract->client_id === $client->id, 403);

        $doc = $contract->documents()->where('kind', 'signed')->first()
            ?: $contract->documents()->where('kind', 'sent')->first();
        abort_if(! $doc || ! Storage::disk('local')->exists($doc->path), 404);

        return response(Storage::disk('local')->get($doc->path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::ascii($contract->agreement_no).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('client_portal');

        return redirect()->route('client.expired');
    }

    private function client(Request $request): Client
    {
        return Client::findOrFail($request->session()->get('client_portal.client_id'));
    }
}
