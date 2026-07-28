<?php

namespace Tests\Feature\Api;

use App\Models\ExchangeRate;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_endpoint_returns_active_public_plans_with_mru_equivalent(): void
    {
        ExchangeRate::create(['base_currency' => 'EUR', 'quote_currency' => 'MRU', 'rate' => 43.0]);

        Plan::factory()->create(['slug' => 'starter', 'name' => 'Starter', 'price_monthly' => 4900, 'active' => true, 'is_public' => true]);
        Plan::factory()->create(['slug' => 'old-plan', 'active' => false]);          // inactif → caché
        Plan::factory()->create(['slug' => 'internal', 'is_public' => false]);       // non public → caché
        Plan::factory()->contactSales()->create(['slug' => 'enterprise', 'name' => 'Enterprise']);

        $response = $this->getJson('/api/v1/plans')->assertOk();
        $data = collect($response->json('data'));

        $slugs = $data->pluck('slug');
        $this->assertTrue($slugs->contains('starter'));
        $this->assertTrue($slugs->contains('enterprise'));
        $this->assertFalse($slugs->contains('old-plan'));
        $this->assertFalse($slugs->contains('internal'));

        // EUR = source de vérité + équivalence MRU indicative (49.00 € × 43 = 2107 MRU).
        $starter = $data->firstWhere('slug', 'starter');
        $this->assertSame('EUR', $starter['currency']);
        $this->assertSame(4900, $starter['price_monthly']);
        $this->assertSame(2107, $starter['display_mru']['monthly']);
        $this->assertEquals(43.0, $starter['display_mru']['exchange_rate']);
        $this->assertSame('MRU', $starter['display_mru']['currency']);

        // Enterprise = contact_sales, sans prix.
        $enterprise = $data->firstWhere('slug', 'enterprise');
        $this->assertTrue($enterprise['contact_sales']);
        $this->assertNull($enterprise['price_monthly']);
    }
}
