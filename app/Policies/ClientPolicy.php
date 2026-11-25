<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Client $c): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Client $c): bool { return true; }
    public function delete(User $user, Client $c): bool { return $user->isAdmin() && ! $c->contracts()->exists(); }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, Client $c): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Client $c): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
}
