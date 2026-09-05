<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le champ « rapport PDF » (report_file) des évènements HSSE : stocké sur le disque
 * privé du tenant, servi par URL signée, et refusé si ce n'est pas un PDF.
 */
class HseReportFileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    public function test_incident_report_file_is_stored_privately(): void
    {
        Storage::fake('tenant-media');
        $t = Tenant::factory()->create(['status' => 'active']);

        $res = $this->actingAs($this->admin($t))->post('/api/v1/incidents', [
            'date'        => '2026-09-05',
            'location'    => 'Quai 3',
            'type'        => 'LTI',
            'severity'    => 'high',
            'description' => 'Test rapport',
            'report_file' => UploadedFile::fake()->create('rapport.pdf', 200, 'application/pdf'),
        ])->assertStatus(201);

        $path = $res->json('report_file');
        $this->assertStringStartsWith("tenants/{$t->id}/incidents/reports/", $path);
        Storage::disk('tenant-media')->assertExists($path);
        $this->assertNotNull($res->json('report_file_url'));
    }

    public function test_environment_report_file_rejects_non_pdf(): void
    {
        Storage::fake('tenant-media');
        $t = Tenant::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin($t))->post('/api/v1/environment', [
            'date'        => '2026-09-05',
            'location'    => 'Bassin',
            'type'        => 'spill',
            'severity'    => 'medium',
            'description' => 'Déversement mineur',
            'report_file' => UploadedFile::fake()->create('feuille.xlsx', 50, 'application/vnd.ms-excel'),
        ])->assertStatus(422)->assertJsonValidationErrors(['report_file']);
    }
}
