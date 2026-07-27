<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Organisation TCN ──────────────────────────────────────────────────
        Organisation::firstOrCreate([], [
            'name'          => 'Terminal à Conteneurs de Nouakchott',
            'short_name'    => 'TCN',
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
                'role'       => 'admin',
                'matricule'  => 'TCN-ADM-001',
                'password'   => Hash::make('Admin@TCN2024'),
                'is_active'  => true,
            ]
        );

        // ── Agent demo ────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'hsse@tcn.mr'],
            [
                'name'      => 'Agent HSSE',
                'role'      => 'agent',
                'matricule' => 'TCN-HSS-001',
                'password'  => Hash::make('Agent@TCN2024'),
                'is_active' => true,
            ]
        );

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
