<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool { return $user->isManager(); }
    public function view(User $user, ActivityLog $a): bool { return $user->isManager(); }
    public function create(User $user): bool { return false; }
    public function update(User $user, ActivityLog $a): bool { return false; }
    public function delete(User $user, ActivityLog $a): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
