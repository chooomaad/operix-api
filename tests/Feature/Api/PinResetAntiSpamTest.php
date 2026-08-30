<?php

namespace Tests\Feature\Api;

use App\Mail\PinResetMail;
use App\Models\PinResetToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PinResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Anti-spam du flux « PIN oublie » : cooldown, plafond horaire, throttle IP.
 *
 * L'objectif : plusieurs clics rapides ne produisent qu'UN email, et un abus est
 * bloque proprement (429) — sans jamais reveler l'existence d'un compte (les
 * limites s'appliquent au matricule SOUMIS, existant ou non).
 */
class PinResetAntiSpamTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $matricule): User
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'matricule' => $matricule,
            'email'     => strtolower($matricule) . '@tcn.mr',
            'is_active' => true,
            'password'  => Hash::make('7391'),
        ]);
    }

    private function forgot(string $matricule)
    {
        return $this->postJson('/api/v1/auth/forgot-pin', ['matricule' => $matricule]);
    }

    public function test_cooldown_un_seul_email_par_minute(): void
    {
        Mail::fake();
        $this->agent('TCN-SPAM-1');

        // Premier clic : accepte, un email en file.
        $this->forgot('TCN-SPAM-1')->assertOk();
        // Deuxieme clic immediat : bloque (429), aucun email supplementaire.
        $this->forgot('TCN-SPAM-1')
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        Mail::assertQueued(PinResetMail::class, 1);
    }

    public function test_vingt_clics_rapides_donnent_un_seul_email(): void
    {
        Mail::fake();
        $this->agent('TCN-SPAM-2');

        for ($i = 0; $i < 20; $i++) {
            $this->forgot('TCN-SPAM-2');
        }

        // Un seul email malgre 20 tentatives.
        Mail::assertQueued(PinResetMail::class, 1);
    }

    public function test_plafond_de_cinq_demandes_par_heure_par_compte(): void
    {
        Mail::fake();
        $this->agent('TCN-SPAM-3');

        // Espace les demandes de 61 s pour franchir le cooldown a chaque fois,
        // tout en restant dans l'heure : les 5 premieres passent, la 6e est bloquee
        // par le plafond horaire.
        for ($i = 0; $i < 5; $i++) {
            $this->forgot('TCN-SPAM-3')->assertOk();
            $this->travel(61)->seconds();
        }
        $this->forgot('TCN-SPAM-3')->assertStatus(429);
    }

    public function test_la_limite_s_applique_a_un_matricule_inexistant_sans_revelation(): void
    {
        Mail::fake();

        // Matricule inconnu : premiere demande = reponse generique 200 ; deuxieme
        // immediate = 429 comme pour un compte existant. Le 429 ne distingue donc
        // pas un compte reel d'un compte fictif.
        $this->forgot('MATRICULE-FANTOME')->assertOk();
        $this->forgot('MATRICULE-FANTOME')->assertStatus(429);

        Mail::assertNothingQueued();
    }

    public function test_throttle_ip_bloque_trop_de_demandes_par_minute(): void
    {
        Mail::fake();

        // Matricules DIFFERENTS (donc cooldown par compte non declenche) depuis la
        // meme IP : la route (throttle:5,1) bloque a la 6e demande dans la minute.
        for ($i = 1; $i <= 5; $i++) {
            $this->forgot("TCN-IP-{$i}")->assertOk();
        }
        $this->forgot('TCN-IP-6')->assertStatus(429);
    }

    public function test_le_lien_expire_apres_30_minutes(): void
    {
        $user  = $this->agent('TCN-TTL-1');
        app(PinResetService::class)->issue($user);

        $token = PinResetToken::where('user_id', $user->id)->first();

        // Fenetre de validite = 30 minutes (a la seconde pres).
        $this->assertEqualsWithDelta(30 * 60, now()->diffInSeconds($token->expires_at), 5);
        $this->assertSame(30, PinResetService::TTL_MINUTES);
    }
}
