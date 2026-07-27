<?php

namespace App\Policies;

use App\Models\EnvironmentReport;
use App\Models\User;

class EnvironmentPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, EnvironmentReport $report): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function update(User $user, EnvironmentReport $report): bool { return $user->isAdmin(); }
    public function delete(User $user, EnvironmentReport $report): bool { return $user->isAdmin(); }
}
