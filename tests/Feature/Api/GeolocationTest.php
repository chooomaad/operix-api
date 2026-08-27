<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Geolocalisation des evenements HSE.
 *
 * Regle directrice : mieux vaut AUCUNE position qu'une position fausse. Une carte
 * HSE sert a envoyer des gens quelque part ; un point errone est plus dangereux
 * qu'un point absent, parce qu'il inspire confiance.
 */
class GeolocationTest extends TestCase
{
    use RefreshDatabase;

    /** Coordonnees du port de Nouakchott — plausibles pour un site portuaire. */
    private const LAT = 18.0735000;
    private const LON = -15.9582000;

    private function agent(): User
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);
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

    // ── Enregistrement nominal ────────────────────────────────────────────────

    public function test_incident_stores_and_returns_its_position(): void
    {
        $agent = $this->agent();

        $response = $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude'             => self::LAT,
                'longitude'            => self::LON,
                'location_accuracy'    => 8.5,
                // Instant relatif, jamais une date figee : une date en dur devient
                // « le futur » des que l'horloge la depasse, et le test se met a
                // echouer sans qu'aucun code n'ait change.
                'location_captured_at' => now()->subMinutes(2)->toIso8601String(),
            ]))
            ->assertStatus(201);

        $response->assertJsonPath('location_point.latitude', self::LAT);
        $response->assertJsonPath('location_point.longitude', self::LON);
        $response->assertJsonPath('location_point.accuracy', 8.5);

        // Le JSON doit porter des nombres, pas les chaines que PostgreSQL renvoie
        // pour un DECIMAL : sans cast, un client devrait reconvertir avant calcul.
        $this->assertIsFloat($response->json('location_point.latitude'));
        $this->assertIsFloat($response->json('location_point.longitude'));
    }

    /**
     * Un signalement sans position reste parfaitement valide : GPS refuse,
     * sous-sol, appareil sans capteur. Le terrain prime sur la donnee.
     */
    public function test_incident_without_position_is_accepted(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload())
            ->assertStatus(201)
            ->assertJsonPath('location_point', null);
    }

    public function test_near_miss_and_environment_also_accept_a_position(): void
    {
        $agent = $this->agent();
        $geo = [
            'latitude'          => self::LAT,
            'longitude'         => self::LON,
            'location_accuracy' => 12.0,
        ];

        $this->actingAs($agent)
            ->postJson('/api/v1/near-miss', array_merge([
                'date'        => '2026-08-27',
                'location'    => 'Zone de chargement',
                'severity'    => 'high',
                'description' => 'Charge suspendue au-dessus d une allee.',
            ], $geo))
            ->assertStatus(201)
            ->assertJsonPath('location_point.latitude', self::LAT);

        $this->actingAs($agent)
            ->postJson('/api/v1/environment', array_merge([
                'date'        => '2026-08-27',
                'location'    => 'Parc a dechets',
                'type'        => 'spill',
                'severity'    => 'low',
                'description' => 'Fuite d huile hydraulique.',
            ], $geo))
            ->assertStatus(201)
            ->assertJsonPath('location_point.longitude', self::LON);
    }

    // ── Refus des positions incoherentes ──────────────────────────────────────

    public function test_half_a_position_is_rejected(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude' => self::LAT,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['longitude']);

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'longitude' => self::LON,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $agent = $this->agent();

        foreach ([[91, 0, 'latitude'], [-91, 0, 'latitude'], [0, 181, 'longitude'], [0, -181, 'longitude']] as [$lat, $lon, $field]) {
            $this->actingAs($agent)
                ->postJson('/api/v1/incidents', $this->incidentPayload([
                    'latitude'  => $lat,
                    'longitude' => $lon,
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors([$field]);
        }
    }

    /**
     * Un rayon d'incertitude de plusieurs kilometres correspond a une position
     * deduite d'une antenne, pas d'un GPS. Sur un site industriel elle ne
     * distingue meme pas deux quais : l'afficher laisserait croire a une
     * precision inexistante.
     */
    public function test_an_unusable_accuracy_is_rejected(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude'          => self::LAT,
                'longitude'         => self::LON,
                'location_accuracy' => 50000,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_accuracy']);
    }

    public function test_a_capture_date_in_the_future_is_rejected(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude'             => self::LAT,
                'longitude'            => self::LON,
                'location_captured_at' => now()->addDays(2)->toIso8601String(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_captured_at']);
    }

    /**
     * Une capture ancienne est legitime : signalement redige hors ligne puis
     * synchronise plus tard. Le passe n'est donc pas borne.
     */
    public function test_an_old_capture_date_is_accepted(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude'             => self::LAT,
                'longitude'            => self::LON,
                'location_captured_at' => now()->subDays(3)->toIso8601String(),
            ]))
            ->assertStatus(201);
    }

    // ── Defense en profondeur : la base refuse aussi ──────────────────────────

    /**
     * La validation HTTP peut etre contournee par un import, un seeder ou une
     * commande console. La contrainte CHECK, elle, ne l'est pas.
     */
    public function test_the_database_itself_refuses_half_a_position(): void
    {
        $agent = $this->agent();

        $this->expectException(QueryException::class);

        DB::table('safety_incidents')->insert([
            'tenant_id'   => $agent->tenant_id,
            'reference'   => 'INC-GEO-0001',
            'date'        => '2026-08-27',
            'location'    => 'Quai 3',
            'type'        => 'LTI',
            'severity'    => 'low',
            'description' => 'Insertion directe, longitude manquante.',
            'status'      => 'open',
            'latitude'    => self::LAT,
            'longitude'   => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_the_database_itself_refuses_out_of_range_coordinates(): void
    {
        $agent = $this->agent();

        $this->expectException(QueryException::class);

        DB::table('safety_incidents')->insert([
            'tenant_id'   => $agent->tenant_id,
            'reference'   => 'INC-GEO-0002',
            'date'        => '2026-08-27',
            'location'    => 'Quai 3',
            'type'        => 'LTI',
            'severity'    => 'low',
            'description' => 'Insertion directe, latitude impossible.',
            'status'      => 'open',
            'latitude'    => 120,
            'longitude'   => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // ── Le client ne choisit jamais son tenant ────────────────────────────────

    /**
     * La position vient du client ; l'appartenance jamais. tenant_id reste hors
     * `fillable`, un envoi explicite ne doit donc rien changer.
     */
    public function test_a_client_supplied_tenant_id_is_ignored(): void
    {
        $agent = $this->agent();
        $other = Tenant::factory()->create(['status' => 'active']);

        $id = $this->actingAs($agent)
            ->postJson('/api/v1/incidents', $this->incidentPayload([
                'latitude'  => self::LAT,
                'longitude' => self::LON,
                'tenant_id' => $other->id,
            ]))
            ->assertStatus(201)
            ->json('id');

        $this->assertDatabaseHas('safety_incidents', [
            'id'        => $id,
            'tenant_id' => $agent->tenant_id,
        ]);
    }
}
