<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Enums\SigningStatus;
use App\Mail\SignatureRequestMail;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\SigningRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Status changes for a contract. Each step is logged in the activity log.
 *
 * Draft → Awaiting approval → Sent → Signed → Active → Expiring → Expired
 *                     ↘ back to Draft (with a note)        any open status → Cancelled
 */
class ContractWorkflow
{
    public function __construct(private ContractRenderer $renderer, private PdfService $pdf, private ContractNotifier $notifier) {}

    /** Values that must be filled before the contract can leave Draft. */
    public function missingValues(Contract $contract): array
    {
        $this->renderer->render($contract, useFrozen: false);
        $missing = $this->renderer->missing;
        foreach (($contract->templateVersion?->fields ?? []) as $f) {
            if (! empty($f['required']) && blank($contract->field_values[$f['key']] ?? null)) {
                $missing[$f['key']] = $f['key'];
            }
        }

        return array_values($missing);
    }

    public function submit(Contract $contract): void
    {
        $this->expect($contract, [ContractStatus::Draft]);
        if ($missing = $this->missingValues($contract)) {
            throw new RuntimeException('Fill these values first: '.implode(', ', $missing));
        }
        $contract->update(['status' => ContractStatus::AwaitingApproval, 'submitted_at' => now(), 'review_note' => null]);
        ActivityLog::record('submitted', $contract, 'Submitted for approval');
        $this->notifier->submitted($contract);
    }

    public function returnToDraft(Contract $contract, string $note): void
    {
        $this->expect($contract, [ContractStatus::AwaitingApproval]);
        $contract->update(['status' => ContractStatus::Draft, 'review_note' => $note]);
        ActivityLog::record('returned', $contract, 'Sent back to draft: '.$note);
        $this->notifier->returned($contract, $note);
    }

    /** Manager approval: freezes the text, stores the approved PDF with its SHA-256 hash, marks as Sent. */
    public function approve(Contract $contract): ContractDocument
    {
        $this->expect($contract, [ContractStatus::AwaitingApproval]);
        if ($missing = $this->missingValues($contract)) {
            throw new RuntimeException('Fill these values first: '.implode(', ', $missing));
        }

        return DB::transaction(function () use ($contract) {
            $html = $this->renderer->render($contract, useFrozen: false);
            $contract->update([
                'frozen_html' => $html,
                'status' => ContractStatus::Sent,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'agreement_date' => $contract->agreement_date ?? now()->toDateString(),
            ]);
            // Re-render with the agreement date now set.
            $contract->update(['frozen_html' => $this->renderer->render($contract->fresh(), useFrozen: false)]);

            $doc = $this->storePdf($contract->fresh(), 'sent');
            ActivityLog::record('approved', $contract, 'Approved and marked as sent', ['sha256' => $doc->sha256]);
            $this->notifier->approved($contract);

            return $doc;
        });
    }

    /**
     * Phase 2: start an online signing session. Pins the exact approved PDF (and its hash),
     * voids any earlier open link, and emails the client a signing link.
     */
    public function createSigningRequest(Contract $contract): \App\Models\SigningRequest
    {
        $this->expect($contract, [ContractStatus::Sent]);
        if (blank($contract->client?->email)) {
            throw new RuntimeException('Add an email address to the client before sending for signing.');
        }
        $sent = $contract->documents()->where('kind', 'sent')->latest()->first();
        if (! $sent || ! Storage::disk('local')->exists($sent->path)) {
            throw new RuntimeException('The approved PDF is missing. Re-approve the contract first.');
        }

        // Only one live link at a time.
        $contract->signingRequests()
            ->whereIn('status', [SigningStatus::Pending->value, SigningStatus::Verified->value])
            ->update(['status' => SigningStatus::Voided->value, 'voided_at' => now()]);

        $request = $contract->signingRequests()->create([
            'token' => \App\Models\SigningRequest::newToken(),
            'email' => $contract->client->email,
            'status' => SigningStatus::Pending,
            'sent_document_id' => $sent->id,
            'sent_sha256' => $sent->sha256,
            'expires_at' => now()->addDays((int) config('portal.signing.link_days')),
            'created_by' => auth()->id(),
        ]);

        Mail::to($request->email)->send(new SignatureRequestMail($request));
        ActivityLog::record('sign_sent', $contract, 'Signing link sent to '.$request->email);

        return $request;
    }

    /** Re-send the signing email for an existing live link (same token). */
    public function resendSigningRequest(SigningRequest $request): void
    {
        if (! $request->isUsable()) {
            throw new RuntimeException('This signing link is no longer valid. Send a new one instead.');
        }
        Mail::to($request->email)->send(new SignatureRequestMail($request));
        ActivityLog::record('sign_resent', $request->contract, 'Signing link re-sent to '.$request->email);
    }

    /** Void a live signing link without signing (e.g. wrong email, re-issue). */
    public function voidSigningRequest(SigningRequest $request): void
    {
        if (! $request->status->isOpen()) {
            throw new RuntimeException('This signing link is already closed.');
        }
        $request->update(['status' => SigningStatus::Voided, 'voided_at' => now()]);
        ActivityLog::record('sign_voided', $request->contract, 'Signing link voided');
    }

    /**
     * Complete an online signature. Requires an OTP-verified, unexpired link and that the
     * exact approved PDF is unchanged. Stores the signed PDF + certificate and marks the contract Signed.
     */
    public function signOnline(SigningRequest $request, array $data): void
    {
        $contract = $request->contract;

        if (! $request->isUsable()) {
            throw new RuntimeException('This signing link is no longer valid.');
        }
        if (! $request->isVerified()) {
            throw new RuntimeException('Please confirm your email with the code before signing.');
        }
        if ($contract->status !== ContractStatus::Sent) {
            throw new RuntimeException('This contract is not awaiting signature.');
        }
        // Tamper check: the exact PDF shown to the signer must still be on file, unchanged.
        $sent = $request->sentDocument;
        if (! $sent || ! Storage::disk('local')->exists($sent->path)
            || hash('sha256', Storage::disk('local')->get($sent->path)) !== $request->sent_sha256) {
            throw new RuntimeException('The document has changed since the link was sent. Please contact us for a new link.');
        }
        if (blank($data['signer_name'] ?? null) || empty($data['intent'])) {
            throw new RuntimeException('Type your full name and confirm you intend to sign.');
        }

        DB::transaction(function () use ($request, $contract, $data) {
            $request->update([
                'signer_name' => trim($data['signer_name']),
                'signer_email' => $request->email,
                'signature_image_path' => $data['signature_image_path'] ?? null,
                'intent_confirmed' => true,
                'ip' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
                'signed_at' => now(),
                'status' => SigningStatus::Signed,
            ]);

            $bytes = $this->pdf->signedContractPdf($contract->fresh(), $request->fresh());
            $path = 'contracts/'.$contract->id.'/'.$contract->agreement_no.'-signed-'.now()->format('YmdHis').'.pdf';
            Storage::disk('local')->put($path, $bytes);
            $contract->documents()->create([
                'kind' => 'signed',
                'path' => $path,
                'original_name' => $contract->agreement_no.'-signed.pdf',
                'sha256' => hash('sha256', $bytes),
                'size' => strlen($bytes),
                'created_by' => null,
            ]);

            $contract->update(['status' => ContractStatus::Signed, 'signed_at' => now()]);
            ActivityLog::record('signed', $contract, 'Signed online by '.$request->signer_name, [
                'ip' => $request->ip,
                'sha256' => $request->sent_sha256,
            ]);
            $this->notifier->signed($request->fresh());
        });

        $this->refreshStatus($contract->fresh());
    }

    /** Phase 1: the client signs on paper/scan; staff upload the signed copy. */
    public function markSigned(Contract $contract, string $uploadedPath, ?string $originalName, ?string $signedOn = null): void
    {
        $this->expect($contract, [ContractStatus::Sent]);
        $disk = Storage::disk('local');
        $contract->documents()->create([
            'kind' => 'signed_upload',
            'path' => $uploadedPath,
            'original_name' => $originalName,
            'sha256' => hash('sha256', $disk->get($uploadedPath)),
            'size' => $disk->size($uploadedPath),
            'created_by' => auth()->id(),
        ]);
        $contract->update(['status' => ContractStatus::Signed, 'signed_at' => $signedOn ?? now()]);
        ActivityLog::record('signed', $contract, 'Signed copy uploaded');
        $this->refreshStatus($contract->fresh());
    }

    public function cancel(Contract $contract, string $reason): void
    {
        if (in_array($contract->status, [ContractStatus::Cancelled, ContractStatus::Expired, ContractStatus::Renewed], true)) {
            throw new RuntimeException('This contract is already closed.');
        }
        $contract->update(['status' => ContractStatus::Cancelled, 'cancelled_at' => now()]);
        ActivityLog::record('cancelled', $contract, 'Cancelled: '.$reason);
    }

    /** Date-driven moves (run daily by the scheduler). */
    public function refreshStatus(Contract $contract): void
    {
        $today = now()->startOfDay();
        $new = $contract->status;
        if (in_array($contract->status, [ContractStatus::Signed, ContractStatus::Active, ContractStatus::Expiring], true)) {
            if ($contract->end_date->lt($today)) {
                $new = ContractStatus::Expired;
            } elseif ($contract->start_date->lte($today)) {
                $new = $contract->end_date->lte($today->copy()->addDays(30)) ? ContractStatus::Expiring : ContractStatus::Active;
            }
        }
        if ($new !== $contract->status) {
            $contract->update(['status' => $new]);
            ActivityLog::record('status', $contract, 'Status changed to '.$new->getLabel());
        }
    }

    public function storePdf(Contract $contract, string $kind): ContractDocument
    {
        $bytes = $this->pdf->contractPdf($contract);
        $path = 'contracts/'.$contract->id.'/'.$contract->agreement_no.'-'.$kind.'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $bytes);

        return $contract->documents()->create([
            'kind' => $kind,
            'path' => $path,
            'original_name' => $contract->agreement_no.'.pdf',
            'sha256' => hash('sha256', $bytes),
            'size' => strlen($bytes),
            'created_by' => auth()->id(),
        ]);
    }

    private function expect(Contract $contract, array $statuses): void
    {
        if (! in_array($contract->status, $statuses, true)) {
            throw new RuntimeException('Not allowed while the contract is '.$contract->status->getLabel().'.');
        }
    }
}
