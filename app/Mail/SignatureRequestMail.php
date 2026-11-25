<?php

namespace App\Mail;

use App\Models\SigningRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Emailed to the client with the link to review and sign a contract online. */
class SignatureRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SigningRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Please sign: '.$this->request->contract->agreement_no);
    }

    public function content(): Content
    {
        $contract = $this->request->contract;

        return new Content(view: 'emails.signing-request', with: [
            'contract' => $contract,
            'url' => $this->request->signingUrl(),
            'expiresAt' => $this->request->expires_at,
            'subject' => 'Please sign: '.$contract->agreement_no,
        ]);
    }
}
