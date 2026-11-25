<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contract files are stored outside the public folder and only served here, after a login check.
 */
class FileController extends Controller
{
    public function contractPdf(Request $request, Contract $contract, PdfService $pdf)
    {
        $this->authorize('view', $contract);

        // Once approved, serve the exact stored PDF (its hash is on record).
        $stored = $contract->documents()->where('kind', 'sent')->latest()->first();
        if ($contract->frozen_html && $stored && Storage::disk('local')->exists($stored->path)) {
            ActivityLog::record('downloaded', $contract, 'Approved PDF opened');

            return $this->inline(Storage::disk('local')->get($stored->path), $contract->agreement_no.'.pdf', $request);
        }

        return $this->inline($pdf->contractPdf($contract), $contract->agreement_no.'-draft.pdf', $request);
    }

    public function document(Request $request, ContractDocument $document)
    {
        $this->authorize('view', $document->contract);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        ActivityLog::record('downloaded', $document->contract, 'Opened '.$document->kindLabel());

        $name = $document->original_name ?: basename($document->path);
        $mime = Storage::disk('local')->mimeType($document->path) ?: 'application/octet-stream';

        return response(Storage::disk('local')->get($document->path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.Str::ascii($name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Sample page so admins can check the letterhead after changing settings. */
    public function letterhead(PdfService $pdf)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $body = '<h2>1. Sample heading</h2><p>This page shows how the CSZ letterhead, fonts and colours look on contracts. Change them under Settings.</p>'.
            '<table><tr><td>Client</td><td>Sample Client (Pvt) Ltd</td></tr><tr><td>Fee</td><td>PKR 7,500 per month</td></tr></table>'.
            '<ul><li>Bullet one</li><li>Bullet two</li></ul>';

        return $this->inline($pdf->make('Sample Agreement', 'Agreement No. CSZ-XXX-0000-0000', $body, 'Letterhead preview'), 'letterhead.pdf', request());
    }

    private function inline(string $bytes, string $name, Request $request)
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.Str::ascii($name).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
