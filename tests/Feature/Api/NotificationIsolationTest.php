<?php

namespace Tests\Feature\Api;

use App\Events\NotificationSent;
use App\Models\AppNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Isolation multi-tenant de l'envoi de notifications.
 *
 * Le modele User ne porte PAS le global scope tenant : les identites
 * d'authentification sont globales. Tout filtrage doit donc etre explicite, et
 * c'est precisement ce qui manquait :
 *
 *  - l'envoi collectif iterait sur TOUS les utilisateurs actifs de la plateforme ;
 *  - l'envoi cible validait `user_id` avec `exists:users,id`, ce qui accepte
 *    l'identifiant d'un utilisateur d'une autre entreprise.
 *
 * Invisible tant que rien n'etait diffuse — le scope filtrait a la lecture. Mais
 * le canal prive `user.{id}` n'autorise que sur l'identifiant, pas sur le tenant :
 * brancher la diffusion transformait ce defaut latent en fuite temps reel.
 */
class NotificationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWith(string $adminMatricule): array
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'company_admin',
            'matricule' => $adminMatricule,
            'is_active' => true,
        ]);

        $agent = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'is_active' => true,
        ]);

        return [$tenant, $admin, $agent];
    }

    private function notificationsFor(Tenant $tenant, int $userId): int
    {
        return app(TenantContext::class)->runWithoutScope(
            fn () => AppNotification::withoutGlobalScopes()
                ->where('notifiable_id', $userId)
                ->count()
        );
    }

    public function test_broadcast_reaches_only_the_senders_tenant(): void
    {
        Event::fake([NotificationSent::class]);

        [$tenantA, $adminA, $agentA] = $this->tenantWith('A-ADM-001');
        [, , $agentB]                = $this->tenantWith('B-ADM-001');

        $this->actingAs($adminA)
            ->postJson('/api/v1/notifications', [
                'title' => 'Exercice evacuation',
                'body'  => 'Exercice prevu demain a 10h.',
                'type'  => 'info',
            ])
            ->assertStatus(201);

        // Les destinataires du tenant emetteur : l'admin lui-meme et son agent.
        $this->assertSame(1, $this->notificationsFor($tenantA, $adminA->id));
        $this->assertSame(1, $this->notificationsFor($tenantA, $agentA->id));

        // Aucun utilisateur d'une autre entreprise ne doit avoir ete touche.
        $this->assertSame(
            0,
            $this->notificationsFor($tenantA, $agentB->id),
            'Un envoi collectif ne doit jamais atteindre un autre tenant.',
        );

        // Et surtout : aucun evenement temps reel vers un canal d'un autre tenant.
        Event::assertNotDispatched(
            NotificationSent::class,
            fn (NotificationSent $e) => $e->recipientId === $agentB->id,
        );
    }

    public function test_targeted_notification_cannot_cross_tenants(): void
    {
        Event::fake([NotificationSent::class]);

        [, $adminA]   = $this->tenantWith('A-ADM-002');
        [, , $agentB] = $this->tenantWith('B-ADM-002');

        // `exists:users,id` accepterait cet identifiant : c'est bien un utilisateur
        // existant. Seule la restriction au tenant courant le refuse.
        $this->actingAs($adminA)
            ->postJson('/api/v1/notifications', [
                'title'   => 'Message cible',
                'body'    => 'Contenu reserve a une autre entreprise.',
                'user_id' => $agentB->id,
            ])
            ->assertStatus(404);

        $this->assertSame(0, AppNotification::withoutGlobalScopes()->count());

        Event::assertNotDispatched(NotificationSent::class);
    }

    public function test_targeted_notification_within_the_tenant_works(): void
    {
        Event::fake([NotificationSent::class]);

        [$tenant, $admin, $agent] = $this->tenantWith('A-ADM-003');

        $this->actingAs($admin)
            ->postJson('/api/v1/notifications', [
                'title'   => 'Consigne',
                'body'    => 'Port du casque obligatoire zone 3.',
                'user_id' => $agent->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('count', 1);

        $this->assertSame(1, $this->notificationsFor($tenant, $agent->id));

        Event::assertDispatched(
            NotificationSent::class,
            fn (NotificationSent $e) => $e->recipientId === $agent->id,
        );
    }

    /**
     * La charge utile diffusee echappe au global scope Eloquent : elle est
     * construite a la main. Ce test verrouille son contenu pour qu'aucun champ
     * interne ne s'y glisse par inadvertance.
     */
    public function test_broadcast_payload_exposes_only_intended_fields(): void
    {
        [$tenant, $admin, $agent] = $this->tenantWith('A-ADM-004');

        $this->actingAs($admin)->postJson('/api/v1/notifications', [
            'title'   => 'Titre',
            'body'    => 'Corps',
            'user_id' => $agent->id,
        ])->assertStatus(201);

        $notification = app(TenantContext::class)->runWithoutScope(
            fn () => AppNotification::withoutGlobalScopes()
                ->where('notifiable_id', $agent->id)
                ->firstOrFail()
        );

        $payload = (new NotificationSent($notification, $agent->id))->broadcastWith();

        $this->assertSame(
            ['id', 'type', 'title', 'body', 'sent_by', 'created_at', 'read_at'],
            array_keys($payload),
        );

        $this->assertArrayNotHasKey('tenant_id', $payload);
        $this->assertArrayNotHasKey('notifiable_id', $payload);
    }
}
