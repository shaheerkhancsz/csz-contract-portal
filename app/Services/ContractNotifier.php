<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractApproved;
use App\Notifications\ContractReturned;
use App\Notifications\ContractSubmitted;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the internal (staff) emails tied to the contract workflow.
 * Recipients and toggles come from Settings; sending is queued.
 */
class ContractNotifier
{
    /** Managers are asked to approve a freshly submitted contract. */
    public function submitted(Contract $contract): void
    {
        if (! $this->on('notify_managers_on_submit')) {
            return;
        }
        $actor = auth()->user();
        $managers = User::activeManagers()->reject(fn (User $u) => $actor && $u->is($actor));
        Notification::send($managers, new ContractSubmitted($contract, $this->actorName()));
    }

    /** The drafter is told their contract was approved. */
    public function approved(Contract $contract): void
    {
        $this->notifyCreator($contract, fn () => new ContractApproved($contract, $this->actorName()));
    }

    /** The drafter is told their contract was sent back, with the reviewer's note. */
    public function returned(Contract $contract, string $note): void
    {
        $this->notifyCreator($contract, fn () => new ContractReturned($contract, $this->actorName(), $note));
    }

    private function notifyCreator(Contract $contract, callable $make): void
    {
        if (! $this->on('notify_creator_on_review')) {
            return;
        }
        $creator = $contract->creator;
        $actor = auth()->user();
        if ($creator && $creator->is_active && ! ($actor && $creator->is($actor))) {
            $creator->notify($make());
        }
    }

    private function on(string $key): bool
    {
        return Settings::enabled('notifications_enabled') && Settings::enabled($key);
    }

    private function actorName(): string
    {
        return auth()->user()?->name ?? 'A team member';
    }
}
