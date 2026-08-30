<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence des en-tetes de securite sur les reponses de l'API.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_en_tetes_de_securite_sont_presents(): void
    {
        // Un point public suffit : le middleware s'applique a toute reponse API.
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_hsts_absent_hors_production(): void
    {
        // HSTS ne doit PAS etre pose en developpement (http local) : il rendrait
        // le site inaccessible apres une premiere visite.
        $this->getJson('/api/health')->assertHeaderMissing('Strict-Transport-Security');
    }
}
