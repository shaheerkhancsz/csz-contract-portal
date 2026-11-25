<?php

namespace App\Mail;

use App\Models\SigningRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A gentle reminder to a client who hasn't signed yet. */
class SignatureReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SigningRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder — please sign '.$this->request->contract->agreement_no);
    }

    public function content(): Content
    {
        $contract = $this->request->contract;

        return new Content(view: 'emails.signing-reminder', with: [
            'contract' => $contract,
            'url' => $this->request->signingUrl(),
            'expiresAt' => $this->request->expires_at,
            'subject' => 'Reminder — please sign '.$contract->agreement_no,
        ]);
    }
}
