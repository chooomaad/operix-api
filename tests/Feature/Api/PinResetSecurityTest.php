<?php

namespace Tests\Feature\Api;

use App\Models\OtpToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Securite du flux « PIN oublie / reinitialisation ».
 *
 * Ces tests verrouillent les corrections de la PHASE B : non-enumeration des
 * comptes, refus des PIN triviaux, usage unique du code. Sans eux, un
 * refactor futur pourrait reintroduire les fuites silencieusement.
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

    public function test_forgot_pin_ne_revele_pas_l_existence_d_un_compte(): void
    {
        $this->activeUser('agent@tcn.mr');

        $known   = $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'agent@tcn.mr']);
        $unknown = $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'personne@tcn.mr']);

        $known->assertOk();
        $unknown->assertOk();

        // Reponse STRICTEMENT identique : un attaquant ne doit pas distinguer un
        // email qui a un compte d'un email qui n'en a pas.
        $this->assertSame(
            $known->json('message'),
            $unknown->json('message'),
            'la reponse de forgot-pin revele l\'existence du compte',
        );
    }

    public function test_forgot_pin_cree_bien_un_code_pour_un_compte_reel(): void
    {
        $this->activeUser('reel@tcn.mr');

        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'reel@tcn.mr'])->assertOk();

        // La reponse est generique, mais le code doit exister en base pour le
        // vrai compte — la discretion ne doit pas casser la fonctionnalite.
        $this->assertDatabaseHas('otp_tokens', ['email' => 'reel@tcn.mr', 'used' => false]);
    }

    public function test_forgot_pin_ne_cree_aucun_code_pour_un_email_inconnu(): void
    {
        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'inconnu@tcn.mr'])->assertOk();

        $this->assertDatabaseMissing('otp_tokens', ['email' => 'inconnu@tcn.mr']);
    }

    public function test_reset_pin_refuse_un_pin_trivial(): void
    {
        $this->activeUser('trivial@tcn.mr');
        OtpToken::create([
            'email' => 'trivial@tcn.mr', 'token' => '123456',
            'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'used' => false,
        ]);

        foreach (['1234', '0000', '1111', '4321'] as $trivial) {
            $this->postJson('/api/v1/auth/reset-pin', [
                'email'                 => 'trivial@tcn.mr',
                'code'                  => '123456',
                'new_pin'               => $trivial,
                'new_pin_confirmation'  => $trivial,
            ])->assertStatus(422)->assertJsonValidationErrors('new_pin');
        }
    }

    public function test_reset_pin_message_d_echec_est_generique(): void
    {
        $this->activeUser('echec@tcn.mr');
        OtpToken::create([
            'email' => 'echec@tcn.mr', 'token' => '111111',
            'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'used' => false,
        ]);

        // Code faux et code visant un email sans compte doivent renvoyer le MEME
        // message — sinon on distingue « code faux » de « compte inexistant ».
        $wrongCode = $this->postJson('/api/v1/auth/reset-pin', [
            'email' => 'echec@tcn.mr', 'code' => '999999',
            'new_pin' => '7391', 'new_pin_confirmation' => '7391',
        ]);
        $noAccount = $this->postJson('/api/v1/auth/reset-pin', [
            'email' => 'fantome@tcn.mr', 'code' => '999999',
            'new_pin' => '7391', 'new_pin_confirmation' => '7391',
        ]);

        $wrongCode->assertStatus(422);
        $noAccount->assertStatus(422);
        $this->assertSame($wrongCode->json('message'), $noAccount->json('message'));
    }

    public function test_reset_pin_valide_change_le_pin_et_est_a_usage_unique(): void
    {
        $user = $this->activeUser('valide@tcn.mr');
        OtpToken::create([
            'email' => 'valide@tcn.mr', 'token' => '654321',
            'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'used' => false,
        ]);

        $first = $this->postJson('/api/v1/auth/reset-pin', [
            'email' => 'valide@tcn.mr', 'code' => '654321',
            'new_pin' => '8264', 'new_pin_confirmation' => '8264',
        ]);
        $first->assertOk();

        // Le nouveau PIN fonctionne reellement.
        $this->assertTrue(Hash::check('8264', $user->fresh()->password));

        // Le code est consomme : un second usage echoue.
        $second = $this->postJson('/api/v1/auth/reset-pin', [
            'email' => 'valide@tcn.mr', 'code' => '654321',
            'new_pin' => '9137', 'new_pin_confirmation' => '9137',
        ]);
        $second->assertStatus(422);
    }
}
