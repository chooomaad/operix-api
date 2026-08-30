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
 * par matricule — jamais une adresse codee en dur ni partagee.
 *
 * Ce test echoue si deux comptes differents recoivent le meme destinataire : c'est
 * la garantie qu'il n'existe aucun fallback (Gmail personnel, compte de test,
 * adresse admin) dans le mecanisme general.
 */
class PinResetRecipientTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $matricule, string $email): User
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'agent',
            'matricule' => $matricule,
            'email'     => $email,
            'is_active' => true,
            'password'  => Hash::make('7391'),
        ]);
    }

    private function forgot(string $matricule)
    {
        return $this->postJson('/api/v1/auth/forgot-pin', ['matricule' => $matricule]);
    }

    public function test_chaque_compte_recoit_son_propre_email(): void
    {
        Mail::fake();

        $a = $this->agent('TCN-A-100', 'alice@tcn.mr');
        $b = $this->agent('TCN-B-200', 'bob@tcn.mr');

        // A demande un reset : le mail part vers l'email de A, jamais celui de B.
        $this->forgot('TCN-A-100')->assertOk();
        Mail::assertQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('alice@tcn.mr'));
        Mail::assertNotQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('bob@tcn.mr'));

        // B demande un reset (matricule different -> pas de cooldown) : email de B.
        $this->forgot('TCN-B-200')->assertOk();
        Mail::assertQueued(PinResetMail::class, fn (PinResetMail $m) => $m->hasTo('bob@tcn.mr'));

        // Au total : deux mails, deux destinataires distincts.
        Mail::assertQueued(PinResetMail::class, 2);
    }

    public function test_un_compte_sans_email_ne_recoit_aucun_mail(): void
    {
        Mail::fake();

        // users.email est NOT NULL ; le cas « sans email exploitable » est la chaine
        // vide. Le garde !$user->email doit alors s'arreter : reponse generique,
        // AUCUN mail, aucun token — sans jamais reveler la situation au client.
        $this->agent('TCN-NOEMAIL', '');

        $this->forgot('TCN-NOEMAIL')->assertOk();

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('pin_reset_tokens', 0);
    }

    public function test_le_destinataire_n_est_jamais_une_adresse_codee_en_dur(): void
    {
        Mail::fake();

        // Un email inhabituel : si un fallback code en dur existait, le mail ne
        // partirait pas vers cette adresse-la.
        $this->agent('TCN-UNIQUE', 'compte.unique.9f3a@example.org');

        $this->forgot('TCN-UNIQUE')->assertOk();

        Mail::assertQueued(
            PinResetMail::class,
            fn (PinResetMail $m) => $m->hasTo('compte.unique.9f3a@example.org'),
        );
    }
}
