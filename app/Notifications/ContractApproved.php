<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the drafter when a manager approves their contract. */
class ContractApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Contract $contract, private string $approvedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approved: '.$this->contract->agreement_no)
            ->view('emails.contract-approved', [
                'contract' => $this->contract,
                'approvedBy' => $this->approvedBy,
                'url' => url('/contracts/'.$this->contract->id),
                'subject' => 'Approved: '.$this->contract->agreement_no,
            ]);
    }
}
