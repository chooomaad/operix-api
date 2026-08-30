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
 * Anti-spam du flux « PIN oublie » (par EMAIL) : cooldown, plafond horaire, throttle IP.
 *
 * Objectif : plusieurs clics rapides ne produisent qu'UN email, et un abus est
 * bloque proprement (429) — sans jamais reveler l'existence d'un compte (les
 * limites s'appliquent a l'EMAIL SOUMIS, existant ou non).
 */
class PinResetAntiSpamTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'email'     => $email,
            'is_active' => true,
            'password'  => Hash::make('7391'),
        ]);
    }

    private function forgot(string $email)
    {
        return $this->postJson('/api/v1/auth/forgot-pin', ['email' => $email]);
    }

    public function test_cooldown_un_seul_email_par_minute(): void
    {
        Mail::fake();
        $this->user('spam1@tcn.mr');

        $this->forgot('spam1@tcn.mr')->assertOk();
        $this->forgot('spam1@tcn.mr')
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        Mail::assertQueued(PinResetMail::class, 1);
    }

    public function test_vingt_clics_rapides_donnent_un_seul_email(): void
    {
        Mail::fake();
        $this->user('spam2@tcn.mr');

        for ($i = 0; $i < 20; $i++) {
            $this->forgot('spam2@tcn.mr');
        }

        Mail::assertQueued(PinResetMail::class, 1);
    }

    public function test_plafond_de_cinq_demandes_par_heure_par_compte(): void
    {
        Mail::fake();
        $this->user('spam3@tcn.mr');

        // 61 s entre chaque demande : franchit le cooldown, reste dans l'heure.
        // Les 5 premieres passent, la 6e est bloquee par le plafond horaire.
        for ($i = 0; $i < 5; $i++) {
            $this->forgot('spam3@tcn.mr')->assertOk();
            $this->travel(61)->seconds();
        }
        $this->forgot('spam3@tcn.mr')->assertStatus(429);
    }

    public function test_la_limite_s_applique_a_un_email_inexistant_sans_revelation(): void
    {
        Mail::fake();

        // Adresse inconnue : 1re demande = 200 generique, 2e immediate = 429 comme
        // pour un compte existant. Le 429 ne distingue pas reel de fictif.
        $this->forgot('fantome@example.com')->assertOk();
        $this->forgot('fantome@example.com')->assertStatus(429);

        Mail::assertNothingQueued();
    }

    public function test_throttle_ip_bloque_trop_de_demandes_par_minute(): void
    {
        Mail::fake();

        // Emails DIFFERENTS (cooldown par compte non declenche) depuis la meme IP :
        // la route (throttle:5,1) bloque a la 6e demande dans la minute.
        for ($i = 1; $i <= 5; $i++) {
            $this->forgot("ip{$i}@tcn.mr")->assertOk();
        }
        $this->forgot('ip6@tcn.mr')->assertStatus(429);
    }

    public function test_le_lien_expire_apres_30_minutes(): void
    {
        $user = $this->user('ttl@tcn.mr');
        app(PinResetService::class)->issue($user);

        $token = PinResetToken::where('user_id', $user->id)->first();

        $this->assertEqualsWithDelta(30 * 60, now()->diffInSeconds($token->expires_at), 5);
        $this->assertSame(30, PinResetService::TTL_MINUTES);
    }
}
