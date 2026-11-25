<?php

namespace App\Policies;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Contract $contract): bool { return true; }
    public function create(User $user): bool { return true; }

    public function update(User $user, Contract $contract): bool
    {
        // Staff edit drafts; managers can also fix a contract waiting for their approval.
        return $contract->status === ContractStatus::Draft
            || ($contract->status === ContractStatus::AwaitingApproval && $user->isManager());
    }

    public function approve(User $user, Contract $contract): bool
    {
        return $user->isManager() && $contract->status === ContractStatus::AwaitingApproval;
    }

    public function cancel(User $user, Contract $contract): bool { return $user->isManager(); }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->isAdmin() && $contract->status === ContractStatus::Draft;
    }

    public function deleteAny(User $user): bool { return $user->isAdmin(); }
    public function restore(User $user, Contract $contract): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Contract $contract): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
}
