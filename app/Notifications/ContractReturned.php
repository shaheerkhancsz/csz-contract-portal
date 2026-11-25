<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the drafter when a manager sends a contract back to draft. */
class ContractReturned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Contract $contract, private string $returnedBy, private string $note) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sent back: '.$this->contract->agreement_no)
            ->view('emails.contract-returned', [
                'contract' => $this->contract,
                'returnedBy' => $this->returnedBy,
                'note' => $this->note,
                'url' => url('/contracts/'.$this->contract->id.'/edit'),
                'subject' => 'Sent back: '.$this->contract->agreement_no,
            ]);
    }
}
