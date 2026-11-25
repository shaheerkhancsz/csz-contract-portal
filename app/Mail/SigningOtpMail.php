<?php

namespace App\Mail;

use App\Models\SigningRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time code used to verify the signer's email before signing.
 * Sent synchronously (not queued) so the code arrives immediately.
 */
class SigningOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public SigningRequest $request, public int $ttlMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your verification code: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signing-otp', with: [
            'code' => $this->code,
            'contract' => $this->request->contract,
            'ttlMinutes' => $this->ttlMinutes,
            'subject' => 'Your verification code',
        ]);
    }
}
