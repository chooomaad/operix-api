<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

class ProvisioningResult
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $admin,
        public readonly ?Subscription $subscription,
        public readonly bool $created,          // false si idempotent (déjà provisionné)
        public readonly ?string $activationToken // token EN CLAIR uniquement au 1er provisioning
    ) {
    }
}
