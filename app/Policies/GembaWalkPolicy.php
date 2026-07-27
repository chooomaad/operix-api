<?php

namespace App\Policies;

use App\Models\GembaWalk;
use App\Models\User;

class GembaWalkPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, GembaWalk $walk): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function update(User $user, GembaWalk $walk): bool { return $user->isAdmin(); }
    public function delete(User $user, GembaWalk $walk): bool { return $user->isAdmin(); }
}
