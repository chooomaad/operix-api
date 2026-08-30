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
 * Securite du flux « PIN oublie / reinitialisation » par lien.
 *
 * Le jeton voyage dans l'URL d'un email ; seul son hash est stocke. Ces tests
 * verrouillent : non-enumeration des comptes, hachage du jeton, usage unique,
 * expiration, invalidation des sessions, refus des PIN triviaux, absence de
 * secret dans les reponses.
 */
class PinResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(string $email): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'email'     => $email,
            'is_active' => true,
            'password'  => Hash::make('7391'),
        ]);
    }

    /** Emet un jeton et renvoie le token EN CLAIR (comme le fait forgotPin). */
    private function issueTokenFor(User $user): string
    {
        return app(PinResetService::class)->issue($user);
    }

    // ── Demande (forgot-pin) ────────────────────────────────────────────────

    public function test_forgot_pin_ne_revele_pas_l_existence_d_un_compte(): void
    {
        Mail::fake();
        $this->activeUser('agent@tcn.mr');

        $known   = $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'agent@tcn.mr']);
        $unknown = $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'personne@tcn.mr']);

        $known->assertOk();
        $unknown->assertOk();

        // Reponse STRICTEMENT identique : un attaquant ne doit pas distinguer un
        // email qui a un compte d'un email qui n'en a pas.
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_forgot_pin_envoie_un_lien_pour_un_compte_reel(): void
    {
        Mail::fake();
        $user = $this->activeUser('reel@tcn.mr');

        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'reel@tcn.mr'])->assertOk();

        // Un jeton existe en base pour ce compte, et l'email est envoye.
        $this->assertDatabaseHas('pin_reset_tokens', ['user_id' => $user->id, 'used_at' => null]);
        Mail::assertSent(PinResetMail::class);
    }

    public function test_forgot_pin_n_envoie_rien_pour_un_email_inconnu(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'inconnu@tcn.mr'])->assertOk();

        $this->assertDatabaseCount('pin_reset_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_le_jeton_est_stocke_hache_jamais_en_clair(): void
    {
        $user  = $this->activeUser('hash@tcn.mr');
        $plain = $this->issueTokenFor($user);

        // Le token EN CLAIR n'apparait nulle part en base ; seul son hash y est.
        $this->assertDatabaseMissing('pin_reset_tokens', ['token_hash' => $plain]);
        $this->assertDatabaseHas('pin_reset_tokens', ['token_hash' => hash('sha256', $plain)]);
    }

    public function test_le_lien_n_expose_ni_pin_ni_hash(): void
    {
        Mail::fake();
        $user = $this->activeUser('mail@tcn.mr');

        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'mail@tcn.mr'])->assertOk();

        Mail::assertSent(PinResetMail::class, function (PinResetMail $mail) {
            // L'URL porte le token en clair (usage unique, court), mais jamais le
            // PIN ni un hash de secret.
            return str_contains($mail->resetUrl, 'token=')
                && !str_contains($mail->resetUrl, '7391');
        });
    }

    // ── Reinitialisation (reset-pin) ────────────────────────────────────────

    public function test_reset_pin_avec_un_jeton_valide_change_le_pin(): void
    {
        $user  = $this->activeUser('ok@tcn.mr');
        $plain = $this->issueTokenFor($user);

        $this->postJson('/api/v1/auth/reset-pin', [
            'token'                => $plain,
            'new_pin'              => '8264',
            'new_pin_confirmation' => '8264',
        ])->assertOk();

        $this->assertTrue(Hash::check('8264', $user->fresh()->password));
    }

    public function test_reset_pin_est_a_usage_unique(): void
    {
        $user  = $this->activeUser('unique@tcn.mr');
        $plain = $this->issueTokenFor($user);

        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => $plain, 'new_pin' => '8264', 'new_pin_confirmation' => '8264',
        ])->assertOk();

        // Rejouer le meme lien echoue : le jeton est consomme.
        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => $plain, 'new_pin' => '9137', 'new_pin_confirmation' => '9137',
        ])->assertStatus(422);
    }

    public function test_reset_pin_refuse_un_jeton_expire(): void
    {
        $user  = $this->activeUser('exp@tcn.mr');
        $plain = $this->issueTokenFor($user);

        // Fait expirer le jeton.
        PinResetToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => $plain, 'new_pin' => '8264', 'new_pin_confirmation' => '8264',
        ])->assertStatus(422);
    }

    public function test_reset_pin_refuse_un_jeton_falsifie(): void
    {
        $this->activeUser('forge@tcn.mr');

        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => str_repeat('a', 64), 'new_pin' => '8264', 'new_pin_confirmation' => '8264',
        ])->assertStatus(422);
    }

    public function test_reset_pin_refuse_un_pin_trivial(): void
    {
        $user  = $this->activeUser('trivial@tcn.mr');

        foreach (['1234', '0000', '1111', '4321'] as $trivial) {
            $plain = $this->issueTokenFor($user);
            $this->postJson('/api/v1/auth/reset-pin', [
                'token'                => $plain,
                'new_pin'              => $trivial,
                'new_pin_confirmation' => $trivial,
            ])->assertStatus(422)->assertJsonValidationErrors('new_pin');
        }
    }

    public function test_reset_pin_exige_la_confirmation(): void
    {
        $user  = $this->activeUser('confirm@tcn.mr');
        $plain = $this->issueTokenFor($user);

        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => $plain, 'new_pin' => '8264', 'new_pin_confirmation' => '9999',
        ])->assertStatus(422)->assertJsonValidationErrors('new_pin');
    }

    public function test_reset_pin_invalide_les_sessions_existantes(): void
    {
        $user  = $this->activeUser('sessions@tcn.mr');
        $user->createToken('web')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $plain = $this->issueTokenFor($user);
        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => $plain, 'new_pin' => '8264', 'new_pin_confirmation' => '8264',
        ])->assertOk();

        // Un PIN reinitialise deconnecte partout.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_aucun_secret_dans_les_reponses(): void
    {
        Mail::fake();
        $user  = $this->activeUser('secret@tcn.mr');

        $forgot = $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'secret@tcn.mr']);
        $plain  = PinResetToken::where('user_id', $user->id)->first();

        // La reponse ne contient ni token, ni hash, ni PIN.
        $body = $forgot->getContent();
        $this->assertStringNotContainsString('token', strtolower($body));
        $this->assertStringNotContainsString('7391', $body);
    }

    public function test_bout_en_bout_ancien_pin_refuse_nouveau_accepte(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user   = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'email'     => 'roundtrip@tcn.mr',
            'matricule' => 'TCN-RT-001',
            'is_active' => true,
            'password'  => Hash::make('7391'),
        ]);

        // 1. Demande de reset.
        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'roundtrip@tcn.mr'])->assertOk();

        // Le lien porte le token en clair ; on le recupere via le mail capture,
        // exactement comme l'utilisateur le lirait dans son email.
        $plainToken = null;
        Mail::assertSent(PinResetMail::class, function (PinResetMail $mail) use (&$plainToken) {
            parse_str(parse_url($mail->resetUrl, PHP_URL_QUERY) ?? '', $q);
            $plainToken = $q['token'] ?? null;
            return $plainToken !== null;
        });
        $this->assertNotNull($plainToken);

        // 2. Reinitialisation avec le token du lien.
        $this->postJson('/api/v1/auth/reset-pin', [
            'token' => $plainToken, 'new_pin' => '8264', 'new_pin_confirmation' => '8264',
        ])->assertOk();

        // 3. L'ancien PIN est refuse.
        $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-RT-001', 'pin' => '7391', 'platform' => 'web',
        ])->assertStatus(401);

        // 4. Le nouveau PIN ouvre bien une session.
        $this->postJson('/api/v1/auth/login', [
            'matricule' => 'TCN-RT-001', 'pin' => '8264', 'platform' => 'web',
        ])->assertOk()->assertJsonStructure(['token']);
    }
}
