<?php

namespace App\Mail;

use App\Models\SigningRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/** Confirmation to the client once they have signed, with the signed PDF attached. */
class ContractSignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SigningRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Signed: '.$this->request->contract->agreement_no);
    }

    public function content(): Content
    {
        $contract = $this->request->contract;

        return new Content(view: 'emails.signed-confirmation', with: [
            'contract' => $contract,
            'signerName' => $this->request->signer_name,
            'signedAt' => $this->request->signed_at,
            'subject' => 'Signed: '.$contract->agreement_no,
        ]);
    }

    public function attachments(): array
    {
        $doc = $this->request->contract->documents()->where('kind', 'signed')->first();
        if (! $doc || ! Storage::disk('local')->exists($doc->path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $doc->path)
                ->as($this->request->contract->agreement_no.'-signed.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
