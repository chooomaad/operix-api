<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Department;
use App\Support\TenantContext;

class TcnSeeder extends Seeder
{
    public function run(): void
    {
        // Organisation unique TCN
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'tcn'],
            [
                'name'          => 'Terminal à Conteneurs de Nouakchott',
                'short_name'    => 'TCN',
                'status'        => 'active',
                'plan'          => 'enterprise',
                'max_employees' => 100000,
                'primary_color' => '#0f2847',
                'country'       => 'MR',
                'timezone'      => 'Africa/Nouakchott',
                'locale'        => 'fr',
            ]
        );

        // Aucun compte de démonstration : les comptes par défaut (admin@tcn.mr /
        // hsse@tcn.mr, mot de passe connu) ont été retirés pour la production.
        // Créer le premier administrateur avec `php artisan operix:create-admin`.

        app(TenantContext::class)->set($tenant->id);

        // Départements TCN
        $departments = ['HSSE', 'Operations', 'Maintenance', 'RH', 'Finance', 'IT', 'Sécurité', 'Administration'];
        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept]);
        }

        $this->command->info('✅ TCN initialisé (organisation + départements, aucun compte de démonstration).');
    }
}
