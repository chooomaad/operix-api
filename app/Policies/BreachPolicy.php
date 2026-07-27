<?php

namespace App\Policies;

use App\Models\Breach;
use App\Models\User;

class BreachPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Breach $breach): bool { return true; }
    public function create(User $user): bool  { return $user->isAdmin(); }
    public function update(User $user, Breach $breach): bool { return $user->isAdmin(); }
    public function delete(User $user, Breach $breach): bool { return $user->isAdmin(); }
}
