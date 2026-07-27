<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visitor;

class VisitorPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Visitor $visitor): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function update(User $user, Visitor $visitor): bool { return $user->isAdmin(); }
    public function delete(User $user, Visitor $visitor): bool { return $user->isAdmin(); }
}
