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

        // ── Admin principal ───────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@tcn.mr'],
            [
                'name'       => 'Administrateur TCN',
                'tenant_id'  => $tenant->id,
                'role'       => 'company_admin',
                'matricule'  => 'TCN-ADM-001',
                'password'   => Hash::make('Admin@TCN2024'),
                'is_active'  => true,
            ]
        );
        $admin->update(['tenant_id' => $tenant->id]);

        // ── Agent demo ────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'hsse@tcn.mr'],
            [
                'name'      => 'Agent HSSE',
                'tenant_id' => $tenant->id,
                'role'      => 'agent',
                'matricule' => 'TCN-HSS-001',
                'password'  => Hash::make('Agent@TCN2024'),
                'is_active' => true,
            ]
        );
        User::where('email', 'hsse@tcn.mr')->update(['tenant_id' => $tenant->id]);

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

        $this->command->info('✓ Organisation TCN, 2 utilisateurs et 10 départements créés.');
    }
}
