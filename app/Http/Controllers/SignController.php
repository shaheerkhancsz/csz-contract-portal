<?php

namespace App\Http\Controllers;

use App\Models\SigningRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Token-authorized PDF streams for the public signing flow. No login: the signing
 * token is the bearer credential, so streams only work for a usable/verified link.
 */
class SignController extends Controller
{
    /** The exact approved PDF presented to the signer. Requires a verified link. */
    public function document(Request $request, string $token)
    {
        $signing = $this->resolve($token);
        abort_unless($signing->isUsable() && $signing->isVerified(), 403);

        $doc = $signing->sentDocument;
        abort_if(! $doc || ! Storage::disk('local')->exists($doc->path), 404);

        return $this->inline(Storage::disk('local')->get($doc->path), $signing->contract->agreement_no.'.pdf');
    }

    /** The signed PDF with its certificate. Available once the link is signed. */
    public function signed(Request $request, string $token)
    {
        $signing = $this->resolve($token);
        abort_unless($signing->status->value === 'signed', 403);

        $doc = $signing->contract->documents()->where('kind', 'signed')->first();
        abort_if(! $doc || ! Storage::disk('local')->exists($doc->path), 404);

        return $this->inline(Storage::disk('local')->get($doc->path), $signing->contract->agreement_no.'-signed.pdf');
    }

    private function resolve(string $token): SigningRequest
    {
        return SigningRequest::with('contract')->where('token', $token)->firstOr(fn () => abort(404));
    }

    private function inline(string $bytes, string $name)
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.\Illuminate\Support\Str::ascii($name).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
