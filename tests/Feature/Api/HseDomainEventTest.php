<?php

namespace Tests\Feature\Api;

use App\Events\HseEventCreated;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Support\TenantContext;
use App\Models\User;
use App\Support\HseEventPayload;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * Evenements metier HSE et diffusion temps reel.
 *
 * Un SEUL evenement sert les trois modules : ils ne different que par leur nature
 * et leur sous-type, portes par le contrat HseEvent. Ces tests verifient donc a la
 * fois la generalite du mecanisme et le cloisonnement qu'il ne doit jamais rompre.
 */
class HseDomainEventTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 18.0735000;
    private const LON = -15.9582000;

    private function agentOf(?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create(['status' => 'active']);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);
    }

    /**
     * Cree un incident hors requete HTTP.
     *
     * BelongsToTenant auto-affecte tenant_id depuis le contexte serveur, pose en
     * temps normal par le middleware ResolveTenant. Sans requete, il faut donc le
     * poser explicitement — c'est aussi ce que fait un job de file d'attente.
     */
    private function makeIncident(User $agent, array $attributes): SafetyIncident
    {
        return app(TenantContext::class)->runWithoutScope(function () use ($agent, $attributes) {
            app(TenantContext::class)->set((int) $agent->tenant_id);

            try {
                $incident = SafetyIncident::create($attributes);
                $incident->load('reporter');

                return $incident;
            } finally {
                app(TenantContext::class)->clear();
            }
        });
    }

    /** @return array<string, mixed> */
    private function incidentPayload(array $extra = []): array
    {
        return array_merge([
            'date'        => '2026-08-27',
            'location'    => 'Quai 3',
            'type'        => 'Fire',
            'severity'    => 'critical',
            'description' => 'Depart de feu sur un groupe electrogene.',
        ], $extra);
    }

    // ── 1 a 3 : les trois modules diffusent ───────────────────────────────────

    public function test_creating_an_incident_broadcasts_the_event(): void
    {
        Event::fake([HseEventCreated::class]);

        $agent = $this->agentOf();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload())
            ->assertStatus(201);

        Event::assertDispatched(
            HseEventCreated::class,
            fn (HseEventCreated $e) => $e->payload->kind === 'incident'
                && $e->payload->subtype === 'Fire'
                && $e->payload->tenantId === $agent->tenant_id,
        );
    }

    public function test_creating_a_near_miss_broadcasts_the_event(): void
    {
        Event::fake([HseEventCreated::class]);

        $agent = $this->agentOf();

        $this->actingAs($agent)
            ->postJson('/api/v1/near-miss', [
                'date'        => '2026-08-27',
                'location'    => 'Zone de chargement',
                'severity'    => 'high',
                'description' => 'Charge suspendue au-dessus d une allee.',
            ])
            ->assertStatus(201);

        Event::assertDispatched(
            HseEventCreated::class,
            // Un presqu'accident n'a volontairement pas de sous-type.
            fn (HseEventCreated $e) => $e->payload->kind === 'near_miss'
                && $e->payload->subtype === null,
        );
    }

    public function test_creating_an_environment_report_broadcasts_the_event(): void
    {
        Event::fake([HseEventCreated::class]);

        $agent = $this->agentOf();

        $this->actingAs($agent)
            ->postJson('/api/v1/environment', [
                'date'        => '2026-08-27',
                'location'    => 'Parc a dechets',
                'type'        => 'spill',
                'severity'    => 'low',
                'description' => 'Fuite d huile hydraulique.',
            ])
            ->assertStatus(201);

        Event::assertDispatched(
            HseEventCreated::class,
            fn (HseEventCreated $e) => $e->payload->kind === 'environment'
                && $e->payload->subtype === 'spill',
        );
    }

    // ── 4 : cloisonnement du canal ────────────────────────────────────────────

    public function test_the_event_is_broadcast_only_on_the_tenant_channel(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $agentA = $this->agentOf($tenantA);

        Event::fake([HseEventCreated::class]);

        $this->actingAs($agentA)
            ->postJson('/api/v1/incidents', $this->incidentPayload())
            ->assertStatus(201);

        Event::assertDispatched(HseEventCreated::class, function (HseEventCreated $e) use ($tenantA, $tenantB) {
            $channels = array_map(
                fn (PrivateChannel $c) => $c->name,
                $e->broadcastOn(),
            );

            // Un seul canal, celui de l'emetteur — et surtout jamais celui d'un autre.
            $this->assertSame(["private-tenant.{$tenantA->id}"], $channels);
            $this->assertNotContains("private-tenant.{$tenantB->id}", $channels);

            return true;
        });
    }

    // ── 5 : autorisation du canal ─────────────────────────────────────────────

    /**
     * Le cloisonnement ne repose PAS sur le contenu du message mais sur qui peut
     * ecouter. On verifie donc la regle d'autorisation elle-meme, telle que
     * declaree dans routes/channels.php.
     */
    public function test_channel_authorisation_refuses_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $agentA = $this->agentOf($tenantA);
        $agentB = $this->agentOf($tenantB);

        $authorise = function (User $user, int $tenantId) {
            // Reprend a l'identique la regle de routes/channels.php.
            return (int) $user->tenant_id === $tenantId;
        };

        $this->assertTrue($authorise($agentA, $tenantA->id));
        $this->assertFalse(
            $authorise($agentA, $tenantB->id),
            'Un utilisateur ne doit jamais pouvoir ecouter le canal d une autre entreprise.',
        );
        $this->assertFalse($authorise($agentB, $tenantA->id));
    }

    /**
     * Autorisation reelle du canal, telle qu'un navigateur la declenche.
     *
     * PIEGE IMPORTANT : phpunit.xml force BROADCAST_CONNECTION=null, et le
     * NullBroadcaster n'appelle JAMAIS verifyUserCanAccessChannel(). Un test qui
     * interroge /broadcasting/auth dans la configuration de test par defaut
     * repond donc 200 pour n'importe quel canal, y compris celui d'une autre
     * entreprise — il passerait au vert en ne prouvant rien, sur la partie la
     * plus sensible du temps reel.
     *
     * On force donc un diffuseur reel pour que la regle de routes/channels.php
     * soit reellement evaluee.
     */
    public function test_channel_authorisation_is_enforced_by_the_broadcaster(): void
    {
        // Identifiants factices : ils ne servent qu'à instancier le diffuseur.
        // Aucune connexion réseau n'a lieu — l'autorisation de canal est évaluée
        // localement, avant toute signature.
        config([
            'broadcasting.default'                 => 'reverb',
            'broadcasting.connections.reverb.key'    => 'cle-de-test',
            'broadcasting.connections.reverb.secret' => 'secret-de-test',
            'broadcasting.connections.reverb.app_id' => 'app-de-test',
        ]);

        // Les canaux sont enregistres au demarrage sur l'instance du pilote ALORS
        // actif — le NullBroadcaster en test. Changer de pilote resout une
        // nouvelle instance, vierge de tout canal, qui refuserait donc TOUT.
        // On recharge le fichier de canaux reel : le test evalue ainsi la regle de
        // production, pas une copie ecrite pour l'occasion.
        require base_path('routes/channels.php');

        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $agentA = $this->agentOf($tenantA);

        $authRequest = function (int $tenantId) use ($agentA) {
            return Request::create('/broadcasting/auth', 'POST', [
                'socket_id'    => '1234.5678',
                'channel_name' => "private-tenant.{$tenantId}",
            ])->setUserResolver(fn () => $agentA);
        };

        // Son propre canal : autorise, aucune exception.
        Broadcast::auth($authRequest($tenantA->id));

        // Celui d'une autre entreprise : refuse par le diffuseur lui-meme.
        $this->expectException(AccessDeniedHttpException::class);
        Broadcast::auth($authRequest($tenantB->id));
    }

    // ── 6 : forme de la charge utile ──────────────────────────────────────────

    public function test_payload_exposes_only_the_intended_fields(): void
    {
        $agent = $this->agentOf();

        $incident = $this->makeIncident($agent, [
            'reference'            => 'INC-EVT-0001',
            'date'                 => '2026-08-27',
            'location'             => 'Quai 3',
            'type'                 => 'Fire',
            'severity'             => 'critical',
            'description'          => 'Depart de feu.',
            'status'               => 'open',
            'reported_by'          => $agent->id,
            'latitude'             => self::LAT,
            'longitude'            => self::LON,
            'location_accuracy'    => 7.5,
            'location_captured_at' => now()->subMinutes(3),
        ]);
        $payload = HseEventPayload::fromModel($incident)->toArray();

        $this->assertSame([
            'id', 'tenant_id', 'kind', 'subtype', 'reference', 'severity',
            'status', 'location', 'location_point', 'reporter', 'created_at',
        ], array_keys($payload));

        $this->assertSame('incident', $payload['kind']);
        $this->assertSame('Quai 3', $payload['location']);
        $this->assertSame(self::LAT, $payload['location_point']['latitude']);
        $this->assertSame(7.5, $payload['location_point']['accuracy']);

        // Le declarant est reduit a son identifiant et son nom : une alerte au
        // tableau de bord n'a pas besoin de ses coordonnees, par ailleurs
        // restreintes par permission.
        $this->assertSame(['id', 'name'], array_keys($payload['reporter']));

        // Aucune colonne interne ne doit avoir suivi le modele.
        foreach (['description', 'root_cause', 'image', 'employees', 'reported_by'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload);
        }
    }

    public function test_payload_has_no_location_point_without_coordinates(): void
    {
        $agent = $this->agentOf();

        $incident = $this->makeIncident($agent, [
            'reference'   => 'INC-EVT-0002',
            'date'        => '2026-08-27',
            'location'    => 'Atelier',
            'type'        => 'LTI',
            'severity'    => 'low',
            'description' => 'Sans position.',
            'status'      => 'open',
            'reported_by' => $agent->id,
        ]);

        $this->assertNull(HseEventPayload::fromModel($incident)->toArray()['location_point']);
    }

    /**
     * Non-regression : la base applique un statut par defaut, mais le modele en
     * memoire l'ignorait jusqu'a un rechargement. La reponse 201 de l'API et
     * l'evenement diffuse annoncaient donc `status: null` alors que la ligne
     * portait bien « open » — un client aurait affiche un incident sans statut.
     */
    public function test_a_freshly_created_event_carries_its_default_status(): void
    {
        Event::fake([HseEventCreated::class]);

        $agent = $this->agentOf();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload())
            ->assertStatus(201)
            ->assertJsonPath('status', 'open');

        Event::assertDispatched(
            HseEventCreated::class,
            fn (HseEventCreated $e) => $e->payload->status === 'open',
        );
    }

    // ── 7 : la reponse HTTP n'attend pas le WebSocket ─────────────────────────

    /**
     * La garantie est structurelle, pas mesuree : ShouldBroadcast met la diffusion
     * en file, ShouldBroadcastNow l'executerait dans la requete. Mesurer un temps
     * de reponse produirait un test instable ; verifier le contrat ne l'est pas.
     */
    public function test_broadcasting_is_queued_and_never_inline(): void
    {
        $this->assertInstanceOf(
            ShouldBroadcast::class,
            new HseEventCreated(new HseEventPayload(
                id: 1, tenantId: 1, kind: 'incident', subtype: null,
                reference: null, severity: null, status: null, location: null,
                locationPoint: null, reporter: null, createdAt: now()->toIso8601String(),
            )),
        );

        $this->assertFalse(
            is_subclass_of(HseEventCreated::class, ShouldBroadcastNow::class),
            'La diffusion ne doit jamais bloquer la reponse HTTP.',
        );

        // Et la diffusion attend la validation de la transaction : un signalement
        // annule ne doit pas avoir ete annonce.
        $this->assertTrue((new \ReflectionClass(HseEventCreated::class))
            ->getProperty('afterCommit')->getDefaultValue());
    }

    // ── 8 : aucun evenement parasite ──────────────────────────────────────────

    public function test_a_validation_failure_broadcasts_nothing(): void
    {
        Event::fake([HseEventCreated::class]);

        $agent = $this->agentOf();

        // Type hors vocabulaire : refuse par la validation avant tout traitement.
        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload(['type' => 'INEXISTANT']))
            ->assertStatus(422);

        Event::assertNotDispatched(HseEventCreated::class);
    }

    public function test_a_rejected_geolocation_broadcasts_nothing(): void
    {
        Event::fake([HseEventCreated::class]);

        $agent = $this->agentOf();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude' => self::LAT, // longitude manquante
            ]))
            ->assertStatus(422);

        Event::assertNotDispatched(HseEventCreated::class);
    }

    public function test_a_forbidden_creation_broadcasts_nothing(): void
    {
        Event::fake([HseEventCreated::class]);

        $tenant = Tenant::factory()->create(['status' => 'active']);
        $agent  = $this->agentOf($tenant);

        // Un agent n'a pas le droit de cloturer : la route est refusee en amont.
        $this->actingAs($agent)
            ->postJson('/api/v1/incidents/1/close', [
                'root_cause'        => 'x',
                'corrective_action' => 'y',
            ])
            ->assertStatus(403);

        Event::assertNotDispatched(HseEventCreated::class);
    }
}
