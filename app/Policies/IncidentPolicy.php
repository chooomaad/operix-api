<?php

namespace App\Policies;

use App\Models\SafetyIncident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, SafetyIncident $incident): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function update(User $user, SafetyIncident $incident): bool { return $user->isAdmin(); }
    public function delete(User $user, SafetyIncident $incident): bool { return $user->isAdmin(); }
}
