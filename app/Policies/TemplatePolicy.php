<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Template $t): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Template $t): bool { return $user->isAdmin(); }
    public function delete(User $user, Template $t): bool { return $user->isAdmin() && ! $t->contracts()->exists(); }
    public function deleteAny(User $user): bool { return false; }
}
