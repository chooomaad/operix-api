<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Department;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $depts    = Department::where('tenant_id', $tenantId)->pluck('id', 'name');

        $employees = [
            ['matricule' => 'TCN-001', 'nom' => 'Ould Ahmed',   'prenom' => 'Mohamed',   'poste' => 'Responsable HSSE',       'type_contrat' => 'CDI',   'department' => 'HSSE',        'gender' => 'M', 'date_embauche' => '2020-01-15'],
            ['matricule' => 'TCN-002', 'nom' => 'Mint Brahim',  'prenom' => 'Fatima',    'poste' => 'Technicienne HSSE',      'type_contrat' => 'CDI',   'department' => 'HSSE',        'gender' => 'F', 'date_embauche' => '2021-03-10'],
            ['matricule' => 'TCN-003', 'nom' => 'Ould Sid',     'prenom' => 'Ahmed',     'poste' => 'Chef Opérations',        'type_contrat' => 'CDI',   'department' => 'Operations',  'gender' => 'M', 'date_embauche' => '2019-06-01'],
            ['matricule' => 'TCN-004', 'nom' => 'Ould Vall',    'prenom' => 'Moussa',    'poste' => 'Conducteur Engin',       'type_contrat' => 'CDI',   'department' => 'Operations',  'gender' => 'M', 'date_embauche' => '2022-02-20'],
            ['matricule' => 'TCN-005', 'nom' => 'Mint Cheikh',  'prenom' => 'Mariem',    'poste' => 'Assistante RH',          'type_contrat' => 'CDI',   'department' => 'RH',          'gender' => 'F', 'date_embauche' => '2021-09-01'],
            ['matricule' => 'TCN-006', 'nom' => 'Ould Taleb',   'prenom' => 'Sidi',      'poste' => 'Technicien Maintenance', 'type_contrat' => 'CDI',   'department' => 'Maintenance', 'gender' => 'M', 'date_embauche' => '2020-11-15'],
            ['matricule' => 'TCN-007', 'nom' => 'Ould Bah',     'prenom' => 'Abdallah',  'poste' => 'Agent Sécurité',         'type_contrat' => 'CDI',   'department' => 'Sécurité',    'gender' => 'M', 'date_embauche' => '2023-01-10'],
            ['matricule' => 'TCN-008', 'nom' => 'Mint Mohamed', 'prenom' => 'Khadijatou','poste' => 'Comptable',              'type_contrat' => 'CDI',   'department' => 'Finance',     'gender' => 'F', 'date_embauche' => '2022-05-01'],
            ['matricule' => 'TCN-009', 'nom' => 'Ould Deye',    'prenom' => 'Ibrahim',   'poste' => 'Développeur IT',         'type_contrat' => 'CDI',   'department' => 'IT',          'gender' => 'M', 'date_embauche' => '2023-03-15'],
            ['matricule' => 'TCN-010', 'nom' => 'Ould Hmade',   'prenom' => 'Cheikh',    'poste' => 'Stagiaire HSSE',         'type_contrat' => 'Stage', 'department' => 'HSSE',        'gender' => 'M', 'date_embauche' => '2026-01-01'],
        ];

        foreach ($employees as $data) {
            Employee::create([
                'tenant_id'     => $tenantId,
                'department_id' => $depts[$data['department']] ?? null,
                'matricule'     => $data['matricule'],
                'nom'           => $data['nom'],
                'prenom'        => $data['prenom'],
                'poste'         => $data['poste'],
                'type_contrat'  => $data['type_contrat'],
                'gender'        => $data['gender'],
                'date_embauche' => $data['date_embauche'],
                'nationalite'   => 'Mauritanienne',
                'is_active'     => true,
            ]);
        }

        $this->command->info('10 employés TCN créés.');
    }
}
