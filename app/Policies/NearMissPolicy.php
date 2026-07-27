<?php

namespace App\Policies;

use App\Models\SafetyNearMiss;
use App\Models\User;

class NearMissPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, SafetyNearMiss $nearMiss): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function update(User $user, SafetyNearMiss $nearMiss): bool { return $user->isAdmin(); }
    public function delete(User $user, SafetyNearMiss $nearMiss): bool { return $user->isAdmin(); }
}
