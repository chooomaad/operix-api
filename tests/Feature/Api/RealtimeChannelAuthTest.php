<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * Autorisation des canaux temps reel (routes/channels.php).
 *
 * Le cloisonnement du temps reel repose sur QUI peut ecouter, pas sur le contenu
 * diffuse. Ces tests evaluent la vraie regle de production, telle qu'un navigateur
 * la declenche sur /broadcasting/auth.
 *
 * PIEGE : phpunit.xml force BROADCAST_CONNECTION=null, et le NullBroadcaster
 * n'appelle JAMAIS la regle d'autorisation — un test laisse en configuration par
 * defaut repondrait 200 pour n'importe quel canal, sans rien prouver. On force
 * donc un diffuseur reel (identifiants factices, aucune connexion reseau) puis on
 * recharge le fichier de canaux reel pour enregistrer les regles sur ce pilote.
 */
class RealtimeChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default'                   => 'reverb',
            'broadcasting.connections.reverb.key'    => 'cle-de-test',
            'broadcasting.connections.reverb.secret' => 'secret-de-test',
            'broadcasting.connections.reverb.app_id' => 'app-de-test',
        ]);

        // Reenregistre les canaux sur l'instance du pilote desormais actif.
        require base_path('routes/channels.php');
    }

    private function authRequest(User $user, string $channel): Request
    {
        return Request::create('/broadcasting/auth', 'POST', [
            'socket_id'    => '1234.5678',
            'channel_name' => $channel,
        ])->setUserResolver(fn () => $user);
    }

    private function assertAllowed(User $user, string $channel): void
    {
        // Une autorisation reussie ne leve pas d'exception.
        Broadcast::auth($this->authRequest($user, $channel));
        $this->addToAssertionCount(1);
    }

    private function assertRefused(User $user, string $channel): void
    {
        try {
            Broadcast::auth($this->authRequest($user, $channel));
            $this->fail("Le canal {$channel} aurait du etre refuse.");
        } catch (AccessDeniedHttpException) {
            $this->addToAssertionCount(1);
        }
    }

    private function activeUser(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'is_active' => true,
        ]);
    }

    // ── Canal utilisateur ─────────────────────────────────────────────────────

    public function test_un_utilisateur_ecoute_son_propre_canal(): void
    {
        $user = $this->activeUser(Tenant::factory()->create(['status' => 'active']));

        $this->assertAllowed($user, "private-user.{$user->id}");
    }

    public function test_un_utilisateur_ne_peut_pas_ecouter_le_canal_d_un_autre(): void
    {
        $user = $this->activeUser(Tenant::factory()->create(['status' => 'active']));

        // Deviner un autre identifiant (« user.999 ») ne donne aucun acces.
        $this->assertRefused($user, 'private-user.999');
        $this->assertRefused($user, 'private-user.' . ($user->id + 1));
    }

    public function test_un_compte_desactive_est_refuse_sur_son_propre_canal(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = $this->activeUser($tenant);
        $user->update(['is_active' => false]);

        // Le jeton reste techniquement valide, mais le compte revoque ne doit plus
        // rien recevoir en temps reel.
        $this->assertRefused($user->fresh(), "private-user.{$user->id}");
    }

    public function test_un_tenant_suspendu_coupe_le_canal_utilisateur(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = $this->activeUser($tenant);
        $tenant->update(['status' => 'suspended']);

        $this->assertRefused($user->fresh(), "private-user.{$user->id}");
    }

    // ── Canal entreprise (presence) ───────────────────────────────────────────

    public function test_un_utilisateur_ecoute_le_canal_de_son_entreprise(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = $this->activeUser($tenant);

        $this->assertAllowed($user, "presence-tenant.{$tenant->id}");
    }

    public function test_un_utilisateur_ne_peut_pas_ecouter_le_canal_d_une_autre_entreprise(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);
        $userA   = $this->activeUser($tenantA);

        $this->assertRefused($userA, "presence-tenant.{$tenantB->id}");
    }

    public function test_un_tenant_suspendu_coupe_son_canal_entreprise(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = $this->activeUser($tenant);
        $tenant->update(['status' => 'suspended']);

        $this->assertRefused($user->fresh(), "presence-tenant.{$tenant->id}");
    }
}
