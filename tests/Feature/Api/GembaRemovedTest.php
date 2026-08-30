<?php

namespace Tests\Feature\Api;

use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Le module Gemba Walk a ete retire du produit (phase D2).
 *
 * Ce test verrouille l'ABSENCE de la fonctionnalite : plus aucune route API ni
 * permission Gemba ne doit subsister. La disparition d'un module se prouve comme
 * sa presence — sinon une route oubliee reviendrait en silence.
 *
 * On teste au niveau du ROUTEUR (et non par un code HTTP) car un catch-all sert
 * la SPA sur tout GET non appareille : le seul fait qu'un GET reponde 200 ne
 * prouverait donc rien. Ce qui compte, c'est qu'AUCUNE route enregistree ne porte
 * plus le prefixe gemba.
 *
 * La table historique `gemba_walks` est volontairement conservee (donnee
 * historique, referencee par les migrations multi-tenant) : le module est rendu
 * inaccessible par le code, pas par une suppression de schema risquee.
 */
class GembaRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucune_route_gemba_n_est_enregistree(): void
    {
        $gembaRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains(strtolower($uri), 'gemba'))
            ->values()
            ->all();

        $this->assertSame(
            [],
            $gembaRoutes,
            'Des routes Gemba subsistent : ' . implode(', ', $gembaRoutes),
        );
    }

    public function test_les_anciens_verbes_ecrivant_gemba_ne_sont_pas_traites(): void
    {
        // Aucune route n'accepte l'ecriture sur /gemba-walks : une tentative
        // POST/PUT/DELETE ne peut donc PAS aboutir a une creation/modification
        // (405 « methode non autorisee » sur le catch-all, jamais 200/201/204).
        foreach ([['post', '/api/v1/gemba-walks'],
                  ['put', '/api/v1/gemba-walks/1'],
                  ['delete', '/api/v1/gemba-walks/1'],
                  ['post', '/api/v1/gemba-walks/1/resolve']] as [$method, $uri]) {
            $status = $this->json(strtoupper($method), $uri)->getStatusCode();
            $this->assertNotContains(
                $status,
                [200, 201, 204],
                "Le verbe {$method} {$uri} ne doit plus etre traite (recu {$status}).",
            );
        }
    }

    public function test_la_permission_gemba_n_existe_plus_dans_la_matrice(): void
    {
        $this->assertArrayNotHasKey('gemba.manage', Permissions::MATRIX);
        $this->assertNotContains('gemba.manage', Permissions::all());
    }
}
