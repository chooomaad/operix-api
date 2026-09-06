<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Organisation TCN ──────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(['slug' => 'tcn'], [
            'name'          => 'Terminal à Conteneurs de Nouakchott',
            'short_name'    => 'TCN',
            'status'        => 'active',
            'plan'          => 'enterprise',
            'max_employees' => 100000,
            'primary_color' => '#0f2847',
            'country'       => 'MR',
            'timezone'      => 'Africa/Nouakchott',
            'locale'        => 'fr',
            'settings'      => [],
        ]);

        // ── Admin de démarrage (OPTIONNEL, sans identifiant en dur) ───────────
        // AUCUN compte de démonstration n'est créé : les anciens comptes
        // admin@tcn.mr / hsse@tcn.mr (mot de passe par défaut connu) ont été retirés
        // pour la production. Un premier administrateur n'est créé QUE si les
        // variables d'environnement sont fournies, et sans jamais réinitialiser un
        // compte existant (firstOrCreate). Sinon, utiliser `php artisan operix:create-admin`.
        $bootstrapMatricule = env('BOOTSTRAP_ADMIN_MATRICULE');
        $bootstrapPin       = env('BOOTSTRAP_ADMIN_PIN');
        if ($bootstrapMatricule && $bootstrapPin) {
            $admin = User::firstOrCreate(
                ['matricule' => $bootstrapMatricule],
                [
                    'name'      => env('BOOTSTRAP_ADMIN_NAME', 'Administrateur'),
                    'email'     => env('BOOTSTRAP_ADMIN_EMAIL'),
                    'tenant_id' => $tenant->id,
                    'role'      => 'company_admin',
                    'password'  => Hash::make($bootstrapPin),
                    'is_active' => true,
                ]
            );
            $admin->update(['tenant_id' => $tenant->id]);
        }

        app(TenantContext::class)->set($tenant->id);

        // ── Départements TCN ──────────────────────────────────────────────────
        $departments = [
            ['name' => 'Direction Générale',          'code' => 'DG'],
            ['name' => 'Opérations Portuaires',       'code' => 'OPS'],
            ['name' => 'HSSE',                        'code' => 'HSSE'],
            ['name' => 'Maintenance',                  'code' => 'MAINT'],
            ['name' => 'Ressources Humaines',          'code' => 'RH'],
            ['name' => 'Finance et Comptabilité',      'code' => 'FIN'],
            ['name' => 'Informatique',                 'code' => 'IT'],
            ['name' => 'Commercial',                   'code' => 'COM'],
            ['name' => 'Logistique',                   'code' => 'LOG'],
            ['name' => 'Sécurité',                     'code' => 'SEC'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept['name']], $dept);
        }

        $this->command->info('✓ Organisation TCN et départements initialisés (aucun compte de démonstration).');
    }
}
