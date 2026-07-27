<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Department;

class TcnSeeder extends Seeder
{
    public function run(): void
    {
        // Organisation unique TCN
        Organisation::firstOrCreate(
            ['short_name' => 'TCN'],
            [
                'name'          => 'Terminal à Conteneurs de Nouakchott',
                'short_name'    => 'TCN',
                'primary_color' => '#0f2847',
                'country'       => 'MR',
                'timezone'      => 'Africa/Nouakchott',
                'locale'        => 'fr',
            ]
        );

        // Admin principal
        User::updateOrCreate(
            ['matricule' => 'TCN-ADM-001'],
            [
                'name'      => 'Administrateur TCN',
                'email'     => 'admin@tcn.mr',
                'password'  => Hash::make('Operix2026'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // Agent HSSE par défaut
        User::updateOrCreate(
            ['matricule' => 'TCN-HSS-001'],
            [
                'name'      => 'Agent HSSE',
                'email'     => 'hsse@tcn.mr',
                'password'  => Hash::make('Operix2026'),
                'role'      => 'agent',
                'is_active' => true,
            ]
        );

        // Départements TCN
        $departments = ['HSSE', 'Operations', 'Maintenance', 'RH', 'Finance', 'IT', 'Sécurité', 'Administration'];
        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept]);
        }

        $this->command->info('');
        $this->command->info('✅ TCN initialisé avec succès !');
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Compte Admin :  TCN-ADM-001');
        $this->command->info('  PIN par défaut: Operix2026');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');
    }
}
