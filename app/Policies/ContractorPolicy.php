<?php

namespace App\Policies;

use App\Models\Contractor;
use App\Models\User;

class ContractorPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Contractor $contractor): bool { return true; }
    public function create(User $user): bool  { return $user->isAdmin(); }
    public function update(User $user, Contractor $contractor): bool { return $user->isAdmin(); }
    public function delete(User $user, Contractor $contractor): bool { return $user->isAdmin(); }
}
