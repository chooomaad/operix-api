<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Employee $employee): bool { return true; }
    public function create(User $user): bool  { return $user->isAdmin(); }
    public function update(User $user, Employee $employee): bool { return $user->isAdmin(); }
    public function delete(User $user, Employee $employee): bool { return $user->isAdmin(); }
}
