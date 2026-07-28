<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\ProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_from_paid_order_creates_full_environment(): void
    {
        $plan  = Plan::factory()->create(['slug' => 'business', 'max_employees' => 500]);
        $order = Order::factory()->create([
            'plan_id'       => $plan->id,
            'company_name'  => 'Port Alpha',
            'contact_name'  => 'Awa Ba',
            'email'         => 'awa@port-alpha.mr',
            'billing_cycle' => 'monthly',
            'status'        => 'paid',
        ]);

        $result = app(ProvisioningService::class)->provisionFromOrder($order);

        $this->assertTrue($result->created);
        $this->assertNotNull($result->activationToken);

        $this->assertDatabaseHas('tenants', ['id' => $result->tenant->id, 'name' => 'Port Alpha', 'status' => 'active', 'plan' => 'business']);
        $this->assertDatabaseHas('users', ['tenant_id' => $result->tenant->id, 'email' => 'awa@port-alpha.mr', 'role' => 'company_admin']);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $result->tenant->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $this->assertDatabaseHas('tenant_activations', ['user_id' => $result->admin->id]);
        $this->assertSame($result->tenant->id, $order->fresh()->tenant_id);
    }

    public function test_provision_from_order_is_idempotent(): void
    {
        $plan  = Plan::factory()->create();
        $order = Order::factory()->create(['plan_id' => $plan->id, 'status' => 'paid']);
        $svc   = app(ProvisioningService::class);

        $first  = $svc->provisionFromOrder($order);
        $second = $svc->provisionFromOrder($order->fresh());

        $this->assertTrue($first->created);
        $this->assertFalse($second->created);                 // second appel = no-op
        $this->assertSame($first->tenant->id, $second->tenant->id);
        $this->assertSame(1, Tenant::count());                // JAMAIS deux entreprises
        $this->assertSame(1, Subscription::where('tenant_id', $first->tenant->id)->count());
    }
}
