<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyDamageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'date'        => '2026-09-05',
            'location'    => 'Quai 3',
            'type'        => 'vehicle',
            'severity'    => 'high',
            'description' => 'Collision chariot élévateur contre conteneur',
        ], $override);
    }

    public function test_create_property_damage_with_reference(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        $this->actingAs($admin)
            ->postJson('/api/v1/property-damage', $this->payload(['estimated_cost' => 25000]))
            ->assertStatus(201)
            ->assertJsonPath('type', 'vehicle')
            ->assertJsonPath('estimated_cost', fn ($v) => (float) $v === 25000.0)
            ->assertJsonPath('reference', fn ($v) => str_starts_with($v ?? '', 'PDM-2026-'));

        $this->assertDatabaseHas('property_damages', [
            'tenant_id' => $t->id,
            'type'      => 'vehicle',
            'severity'  => 'high',
        ]);
    }

    public function test_reference_is_tenant_scoped(): void
    {
        $a = Tenant::factory()->create(['status' => 'active']);
        $b = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin($a))->postJson('/api/v1/property-damage', $this->payload())
            ->assertStatus(201)->assertJsonPath('reference', 'PDM-2026-0001');
        $this->actingAs($this->admin($b))->postJson('/api/v1/property-damage', $this->payload())
            ->assertStatus(201)->assertJsonPath('reference', 'PDM-2026-0001');
    }

    public function test_unknown_type_rejected_422(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin($t))
            ->postJson('/api/v1/property-damage', $this->payload(['type' => 'meteorite']))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_close_requires_corrective_action(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $id = $this->actingAs($admin)->postJson('/api/v1/property-damage', $this->payload())->json('id');

        $this->actingAs($admin)->postJson("/api/v1/property-damage/{$id}/close", [])
            ->assertStatus(422)->assertJsonValidationErrors(['corrective_action']);

        $this->actingAs($admin)->postJson("/api/v1/property-damage/{$id}/close", [
            'corrective_action' => 'Remise en état + rappel consignes',
        ])->assertStatus(200)->assertJsonFragment(['status' => 'closed']);
    }

    public function test_agent_can_report_but_not_delete(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $agent = User::factory()->create(['tenant_id' => $t->id, 'role' => 'agent', 'is_active' => true]);

        $id = $this->actingAs($agent)->postJson('/api/v1/property-damage', $this->payload())
            ->assertStatus(201)->json('id');

        $this->actingAs($agent)->deleteJson("/api/v1/property-damage/{$id}")->assertStatus(403);
    }

    public function test_report_file_pdf_is_attached_and_served(): void
    {
        Storage::fake('tenant-media');
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);

        $res = $this->actingAs($admin)->post('/api/v1/property-damage', $this->payload([
            'report_file' => UploadedFile::fake()->create('rapport.pdf', 300, 'application/pdf'),
        ]))->assertStatus(201);

        $path = $res->json('report_file');
        $this->assertStringStartsWith("tenants/{$t->id}/property-damage/reports/", $path);
        Storage::disk('tenant-media')->assertExists($path);
        $this->assertNotNull($res->json('report_file_url'));
    }

    public function test_report_file_rejects_non_pdf(): void
    {
        Storage::fake('tenant-media');
        $t = Tenant::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin($t))->post('/api/v1/property-damage', $this->payload([
            'report_file' => UploadedFile::fake()->image('photo.jpg'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['report_file']);
    }
}
