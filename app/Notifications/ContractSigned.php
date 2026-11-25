<?php

namespace App\Notifications;

use App\Models\SigningRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the drafter and managers when a client signs a contract online. */
class ContractSigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private SigningRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contract = $this->request->contract;

        return (new MailMessage)
            ->subject('Signed: '.$contract->agreement_no)
            ->view('emails.contract-signed', [
                'contract' => $contract,
                'signerName' => $this->request->signer_name,
                'signedAt' => $this->request->signed_at,
                'url' => url('/contracts/'.$contract->id),
                'subject' => 'Signed: '.$contract->agreement_no,
            ]);
    }
}
