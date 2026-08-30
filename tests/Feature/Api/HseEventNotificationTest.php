<?php

namespace Tests\Feature\Api;

use App\Events\HseEventCreated;
use App\Listeners\SendHseEventNotifications;
use App\Models\AppNotification;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HseEventPayload;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Persistance d'un evenement HSE dans le centre de notifications.
 *
 * La diffusion live (canal tenant.{id}) est ephemere ; le cahier des charges
 * exige qu'une notification importante laisse une TRACE persistee, retrouvable
 * apres coup. Ces tests verifient que la trace est creee pour les bons
 * destinataires — et jamais pour une autre entreprise.
 */
class HseEventNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Tenant $tenant, string $role): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function incidentFor(User $reporter, string $severity = 'critical'): SafetyIncident
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($reporter, $severity) {
            app(TenantContext::class)->set((int) $reporter->tenant_id);

            try {
                $incident = SafetyIncident::create([
                    'reference'   => 'INC-NOTIF-0001',
                    'date'        => '2026-08-29',
                    'location'    => 'Quai 3',
                    'type'        => 'Fire',
                    'severity'    => $severity,
                    'description' => 'Depart de feu.',
                    'status'      => 'open',
                    'reported_by' => $reporter->id,
                ]);
                $incident->load('reporter');

                return $incident;
            } finally {
                app(TenantContext::class)->clear();
            }
        });
    }

    private function fire(SafetyIncident $incident): void
    {
        $payload = HseEventPayload::fromModel($incident);
        app(SendHseEventNotifications::class)->handle(new HseEventCreated($payload));
    }

    public function test_une_notification_persistee_est_creee_pour_les_habilites(): void
    {
        $tenant   = Tenant::factory()->create(['status' => 'active']);
        $reporter = $this->userOf($tenant, 'agent');
        $manager  = $this->userOf($tenant, 'hsse_manager');
        $admin    = $this->userOf($tenant, 'company_admin');

        $this->fire($this->incidentFor($reporter));

        // Le manager et l'admin (habilites incidents.view) sont notifies.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $manager->id,
            'notifiable_type' => User::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
        ]);

        // L'auteur du signalement ne se notifie pas lui-meme.
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $reporter->id,
        ]);
    }

    public function test_la_notification_porte_le_type_et_le_lien_vers_la_ressource(): void
    {
        $tenant   = Tenant::factory()->create(['status' => 'active']);
        $reporter = $this->userOf($tenant, 'agent');
        $admin    = $this->userOf($tenant, 'company_admin');

        $incident = $this->incidentFor($reporter, 'critical');
        $this->fire($incident);

        $notif = AppNotification::where('notifiable_id', $admin->id)->firstOrFail();

        // Gravite critique -> type « alert » ; lien vers la ressource incident.
        $this->assertSame('alert', $notif->data['type']);
        $this->assertSame('incident', $notif->data['resource_kind']);
        $this->assertSame("/incidents/{$incident->id}", $notif->data['link']);
        // Aucune donnee sensible (description, coordonnees) dans la notification.
        $this->assertArrayNotHasKey('description', $notif->data);
    }

    public function test_aucune_notification_ne_franchit_la_frontiere_du_tenant(): void
    {
        $tenantA  = Tenant::factory()->create(['status' => 'active']);
        $tenantB  = Tenant::factory()->create(['status' => 'active']);
        $reporter = $this->userOf($tenantA, 'agent');
        $this->userOf($tenantA, 'company_admin');       // destinataire legitime A
        $intrusB  = $this->userOf($tenantB, 'company_admin'); // ne doit RIEN recevoir

        $this->fire($this->incidentFor($reporter));

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $intrusB->id,
        ]);

        // La notification creee porte bien le tenant de A, jamais celui de B.
        $this->assertSame(0, AppNotification::withoutGlobalScopes()
            ->where('tenant_id', $tenantB->id)->count());
        $this->assertGreaterThan(0, AppNotification::withoutGlobalScopes()
            ->where('tenant_id', $tenantA->id)->count());
    }

    public function test_une_creation_ne_produit_pas_de_notification_en_double(): void
    {
        // Garde-fou contre un double abonnement du listener (auto-decouverte +
        // enregistrement explicite) : une creation d'incident doit produire
        // EXACTEMENT une notification par destinataire habilite, jamais deux.
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->userOf($tenant, 'agent');
        $admin  = $this->userOf($tenant, 'company_admin');

        $this->actingAs($agent)->postJson('/api/v1/incidents', [
            'date'        => '2026-08-29',
            'location'    => 'Quai 3',
            'type'        => 'Fire',
            'severity'    => 'critical',
            'description' => 'Depart de feu sur un groupe electrogene.',
        ])->assertStatus(201);

        $this->assertSame(1, AppNotification::withoutGlobalScopes()
            ->where('notifiable_id', $admin->id)->count());
    }

    public function test_un_destinataire_desactive_n_est_pas_notifie(): void
    {
        $tenant   = Tenant::factory()->create(['status' => 'active']);
        $reporter = $this->userOf($tenant, 'agent');
        $inactive = $this->userOf($tenant, 'company_admin');
        $inactive->update(['is_active' => false]);

        $this->fire($this->incidentFor($reporter));

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $inactive->id,
        ]);
    }
}
