<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to managers when a staff member submits a contract for approval. */
class ContractSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Contract $contract, private string $submittedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval needed: '.$this->contract->agreement_no)
            ->view('emails.contract-submitted', [
                'contract' => $this->contract,
                'submittedBy' => $this->submittedBy,
                'url' => url('/contracts/'.$this->contract->id),
                'subject' => 'Approval needed: '.$this->contract->agreement_no,
            ]);
    }
}
