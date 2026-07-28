<?php

namespace Tests\Feature\Api;

use App\Models\DemoRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'company_name'   => 'Port Alpha',
            'contact_name'   => 'Awa Ba',
            'email'          => 'awa@port-alpha.mr',
            'employee_count' => 120,
            'message'        => 'Intéressés par une démo HSSE.',
        ], $override);
    }

    public function test_public_can_submit_demo_request_with_server_reference(): void
    {
        $response = $this->postJson('/api/v1/demo-requests', $this->payload())
            ->assertStatus(201);

        $reference = $response->json('reference');
        $this->assertMatchesRegularExpression('/^DEMO-\d{4}-\d{6}$/', $reference);

        $this->assertDatabaseHas('demo_requests', [
            'reference'    => $reference,
            'email'        => 'awa@port-alpha.mr',
            'status'       => 'new',
        ]);
    }

    public function test_demo_request_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/demo-requests', $this->payload(['email' => "user{$i}@x.mr"]))
                ->assertStatus(201);
        }

        // 6e requête dans la même minute → bloquée.
        $this->postJson('/api/v1/demo-requests', $this->payload(['email' => 'user6@x.mr']))
            ->assertStatus(429);
    }

    public function test_superadmin_can_list_and_update_demo_status(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $demo = DemoRequest::create($this->payload() + ['status' => 'new']);

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/superadmin/demo-requests')
            ->assertOk()
            ->assertJsonFragment(['reference' => $demo->reference]);

        $this->actingAs($superAdmin)
            ->putJson("/api/v1/superadmin/demo-requests/{$demo->id}/status", ['status' => 'approved'])
            ->assertOk();

        $this->assertDatabaseHas('demo_requests', [
            'id'         => $demo->id,
            'status'     => 'approved',
            'handled_by' => $superAdmin->id,
        ]);
    }

    public function test_company_admin_cannot_access_superadmin_demo_requests(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'company_admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/superadmin/demo-requests')
            ->assertStatus(403);
    }
}
