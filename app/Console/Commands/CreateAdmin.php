<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crée (ou réinitialise le PIN d') un compte administrateur RÉEL, sans identifiant
 * en dur — remplace les anciens comptes de démonstration (admin@tcn.mr / hsse@tcn.mr).
 *
 * Exemples :
 *   php artisan operix:create-admin ADM-DGA --name="Amina Sy" --email=a.sy@tcn.mr --pin=Secret123!
 *   php artisan operix:create-admin ADM-DGA          (PIN généré et affiché une fois)
 *   php artisan operix:create-admin ADM-DGA --force   (réinitialise le PIN d'un compte existant)
 */
class CreateAdmin extends Command
{
    protected $signature = 'operix:create-admin
        {matricule : Matricule de connexion (ex. ADM-DGA)}
        {--name= : Nom affiché}
        {--email= : Adresse e-mail (optionnelle)}
        {--pin= : PIN/mot de passe (généré si absent)}
        {--role=company_admin : company_admin|hsse_manager|supervisor|agent}
        {--tenant=tcn : Slug de l\'organisation}
        {--force : Autorise la réinitialisation du PIN si le matricule existe déjà}';

    protected $description = 'Crée un administrateur réel (ou réinitialise son PIN), sans identifiant par défaut.';

    public function handle(): int
    {
        $matricule = trim((string) $this->argument('matricule'));
        $role      = (string) $this->option('role');
        $allowed   = ['company_admin', 'hsse_manager', 'supervisor', 'agent'];

        if (! in_array($role, $allowed, true)) {
            $this->error("Rôle invalide : {$role}. Attendu : " . implode(', ', $allowed) . '.');
            return self::FAILURE;
        }

        $tenant = Tenant::where('slug', $this->option('tenant'))->first();
        if (! $tenant) {
            $this->error("Organisation introuvable (slug: {$this->option('tenant')}). Lancez d'abord les seeders.");
            return self::FAILURE;
        }
        app(TenantContext::class)->set($tenant->id);

        $existing = User::where('matricule', $matricule)->first();
        if ($existing && ! $this->option('force')) {
            $this->error("Un compte avec le matricule {$matricule} existe déjà. Utilisez --force pour réinitialiser son PIN.");
            return self::FAILURE;
        }

        // PIN fourni, ou généré (12 caractères) et affiché UNE fois.
        $pin       = (string) ($this->option('pin') ?: Str::password(12, true, true, false));
        $generated = ! $this->option('pin');

        $attributes = [
            'name'      => (string) ($this->option('name') ?: $existing?->name ?: 'Administrateur'),
            'email'     => $this->option('email') ?: $existing?->email,
            'tenant_id' => $tenant->id,
            'role'      => $role,
            'password'  => Hash::make($pin),
            'is_active' => true,
        ];

        $user = User::updateOrCreate(['matricule' => $matricule], $attributes);
        $user->update(['tenant_id' => $tenant->id]);

        $this->info($existing ? "✓ Compte {$matricule} mis à jour." : "✓ Compte {$matricule} créé.");
        $this->line("  Nom      : {$user->name}");
        $this->line("  Rôle     : {$user->role}");
        $this->line("  Org.     : {$tenant->name}");
        if ($generated) {
            $this->newLine();
            $this->warn("  PIN généré (à noter, non ré-affiché) : {$pin}");
        }

        return self::SUCCESS;
    }
}
