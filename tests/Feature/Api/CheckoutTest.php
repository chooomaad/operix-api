<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_computes_amount_server_side_and_ignores_client_values(): void
    {
        $plan = Plan::factory()->create(['slug' => 'starter', 'price_monthly' => 4900, 'currency' => 'EUR', 'active' => true]);

        $response = $this->postJson('/api/v1/checkout', [
            'plan_slug'     => 'starter',
            'billing_cycle' => 'monthly',
            'company_name'  => 'Port Alpha',
            'contact_name'  => 'Awa Ba',
            'email'         => 'awa@port-alpha.mr',
            // Tentatives de spoofing — doivent être ignorées :
            'amount'        => 1,
            'currency'      => 'USD',
            'tenant_id'     => 999,
        ])->assertStatus(201);

        $reference = $response->json('reference');
        $this->assertMatchesRegularExpression('/^OPX-\d{4}-\d{6}$/', $reference);
        $response->assertJsonPath('amount', 4900);
        $response->assertJsonPath('currency', 'EUR');

        $this->assertDatabaseHas('orders', [
            'reference' => $reference,
            'plan_id'   => $plan->id,
            'amount'    => 4900,
            'currency'  => 'EUR',
            'status'    => 'pending',
            'tenant_id' => null,
        ]);
    }

    public function test_enterprise_contact_sales_plan_is_not_checkoutable(): void
    {
        Plan::factory()->contactSales()->create(['slug' => 'enterprise']);

        $this->postJson('/api/v1/checkout', [
            'plan_slug'     => 'enterprise',
            'billing_cycle' => 'monthly',
            'company_name'  => 'Big Corp',
            'contact_name'  => 'CEO',
            'email'         => 'ceo@big.mr',
        ])->assertStatus(422);
    }

    public function test_inactive_plan_is_not_checkoutable(): void
    {
        Plan::factory()->create(['slug' => 'legacy', 'active' => false]);

        $this->postJson('/api/v1/checkout', [
            'plan_slug'     => 'legacy',
            'billing_cycle' => 'monthly',
            'company_name'  => 'X',
            'contact_name'  => 'Y',
            'email'         => 'y@x.mr',
        ])->assertStatus(422);
    }
}
