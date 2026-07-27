<?php

namespace App\Policies;

use App\Models\PermitToWork;
use App\Models\User;

class PermitToWorkPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, PermitToWork $permit): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function update(User $user, PermitToWork $permit): bool { return $user->isAdmin(); }
    public function delete(User $user, PermitToWork $permit): bool { return $user->isAdmin(); }
    public function approve(User $user, PermitToWork $permit): bool { return $user->isAdmin(); }
}
