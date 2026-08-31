<?php

namespace Tests\Feature\Api;

use App\Models\Certification;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Certifications / Formations / Visites médicales : création (bug « type field is
 * required » corrigé), justificatif image optionnel stocké, et présence de l'image
 * dans le PDF de profil.
 */
class EmployeeRecordsAndImageTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('operix.media_disk', 'tenant-media');
        Storage::fake($this->disk);
    }

    private function admin(Tenant $t): User
    {
        return User::factory()->create(['tenant_id' => $t->id, 'role' => 'company_admin', 'is_active' => true]);
    }

    private function emp(Tenant $t): Employee
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($t) {
            app(TenantContext::class)->set($t->id);
            try { return Employee::factory()->create(['tenant_id' => $t->id]); }
            finally { app(TenantContext::class)->clear(); }
        });
    }

    public function test_creation_certification_formation_visite_sans_image(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $emp = $this->emp($t);

        // Certification : seul `titre` + `date_obtention` requis (plus de `type` requis)
        $this->actingAs($admin)->postJson("/api/v1/employees/{$emp->id}/certifications", [
            'titre' => 'ATEX', 'organisme' => 'ONG', 'date_obtention' => '2026-08-31',
        ])->assertStatus(201)->assertJsonPath('titre', 'ATEX');

        // Formation : type/statut non fournis → défauts appliqués (colonnes NOT NULL)
        $this->actingAs($admin)->postJson("/api/v1/employees/{$emp->id}/formations", [
            'titre' => 'Secourisme', 'date_debut' => '2026-08-01',
        ])->assertStatus(201)->assertJsonPath('titre', 'Secourisme');

        // Visite médicale : type/resultat non fournis → défauts appliqués
        $this->actingAs($admin)->postJson("/api/v1/employees/{$emp->id}/medical-visits", [
            'date' => '2026-08-15',
        ])->assertStatus(201);
    }

    public function test_certification_avec_image_stocke_le_fichier(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $emp = $this->emp($t);

        $file = UploadedFile::fake()->create('cert.jpg', 20, 'image/jpeg');

        $id = $this->actingAs($admin)->post("/api/v1/employees/{$emp->id}/certifications", [
            'titre' => 'Travail en hauteur', 'date_obtention' => '2026-08-31',
            'image' => $file,
        ])->assertStatus(201)->json('id');

        $cert = Certification::withoutGlobalScopes()->find($id);
        $this->assertNotNull($cert->document, 'Le chemin du justificatif doit être enregistré.');
        Storage::disk($this->disk)->assertExists($cert->document);
        $this->assertNotNull($cert->image_url); // URL exposée à l'API
    }

    public function test_le_pdf_profil_integre_l_image_du_justificatif(): void
    {
        $t = Tenant::factory()->create(['status' => 'active']);
        $admin = $this->admin($t);
        $emp = $this->emp($t);

        // Vraie image PNG 1x1 écrite directement sur le disque
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $path = "certifications/{$t->id}/cert.png";
        Storage::disk($this->disk)->put($path, $png);

        app(TenantContext::class)->runWithoutScope(function () use ($t, $emp, $path) {
            app(TenantContext::class)->set($t->id);
            try {
                Certification::create([
                    'employee_id' => $emp->id, 'titre' => 'ATEX',
                    'date_obtention' => '2026-08-31', 'document' => $path,
                ]);
            } finally { app(TenantContext::class)->clear(); }
        });

        $res = $this->actingAs($admin)->get("/api/v1/reports/employees/{$emp->id}/profile");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));

        // Le rendu HTML de la vue doit intégrer l'image en base64
        $certs = Certification::where('employee_id', $emp->id)->get();
        $certs->each(fn ($c) => $c->img_data = 'data:image/png;base64,' . base64_encode($png));
        $html = view('pdf.employee_profile', [
            'title' => 'P', 'orgName' => 'TCN', 'orgShort' => 'TCN', 'orgLogo' => null, 'brandColor' => '#0f2847',
            'employee' => $emp, 'incidents' => collect(), 'nearMiss' => collect(),
            'breaches' => collect(), 'environment' => collect(),
            'formations' => collect(), 'certifications' => $certs, 'medicalVisits' => collect(),
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('<img', $html);
    }
}
