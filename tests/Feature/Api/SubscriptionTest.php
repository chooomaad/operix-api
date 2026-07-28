<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_plan_accepts_business_after_reconciliation(): void
    {
        // La contrainte CHECK doit désormais autoriser 'business' (ex-'pro').
        $tenant = Tenant::factory()->create(['plan' => 'business']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'plan' => 'business']);
    }

    public function test_subscription_links_tenant_and_plan(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $plan   = Plan::factory()->create(['slug' => 'business']);

        $sub = Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => 'monthly',
            'starts_at'     => now(),
        ]);

        $this->assertTrue($sub->isActive());
        $this->assertSame($tenant->id, $sub->tenant->id);
        $this->assertSame($plan->id, $sub->plan->id);
    }
}
