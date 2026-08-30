<?php

namespace Tests\Feature\Api;

use App\Mail\PinResetMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Le destinataire du mail de reset est TOUJOURS l'email PROPRE du compte trouve
 * par l'adresse saisie — jamais une adresse codee en dur ni partagee.
 *
 * Ce test echoue si deux comptes differents recoivent le meme destinataire : c'est
 * la garantie qu'il n'existe aucun fallback (Gmail personnel, compte de test,
 * adresse admin) dans le mecanisme general.
 */
class PinResetRecipientTest extends TestCase
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

    public function test_chaque_compte_recoit_son_propre_email(): void
    {
        Mail::fake();

        $this->user('alice@tcn.mr');
        $this->user('bob@tcn.mr');

        // A demande un reset : le mail part vers l'email de A, jamais celui de B.
        $this->forgot('alice@tcn.mr')->assertOk();
        Mail::assertQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('alice@tcn.mr'));
        Mail::assertNotQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('bob@tcn.mr'));

        // B demande un reset (email different -> pas de cooldown) : email de B.
        $this->forgot('bob@tcn.mr')->assertOk();
        Mail::assertQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('bob@tcn.mr'));

        // Au total : deux mails, deux destinataires distincts.
        Mail::assertQueued(PinResetMail::class, 2);
    }

    public function test_un_email_inexistant_ne_declenche_aucun_mail(): void
    {
        Mail::fake();

        // Aucun compte pour cette adresse : reponse generique, AUCUN mail, aucun
        // token — sans jamais reveler que le compte n'existe pas.
        $this->forgot('does-not-exist@example.com')->assertOk();

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('pin_reset_tokens', 0);
    }

    public function test_le_destinataire_n_est_jamais_une_adresse_codee_en_dur(): void
    {
        Mail::fake();

        $this->user('compte.unique.9f3a@example.org');

        $this->forgot('compte.unique.9f3a@example.org')->assertOk();

        Mail::assertQueued(
            PinResetMail::class,
            fn (PinResetMail $m) => $m->hasTo('compte.unique.9f3a@example.org'),
        );
    }

    public function test_email_en_majuscules_et_avec_espaces_fonctionne(): void
    {
        Mail::fake();

        $this->user('casse@tcn.mr');

        // Saisie « sale » : majuscules + espaces. La normalisation (trim + lower)
        // doit retrouver le compte et envoyer a son adresse reelle.
        $this->forgot('  CASSE@TCN.MR  ')->assertOk();

        Mail::assertQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('casse@tcn.mr'));
    }

    public function test_email_invalide_est_rejete_par_la_validation(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-pin', ['email' => 'pas-un-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        Mail::assertNothingQueued();
    }
}
