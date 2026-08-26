<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EnvironmentReport;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Second tenant complet, distinct de TCN.
 *
 * Jusqu'ici un seul tenant était semé : l'isolation multi-tenant était prouvée par
 * les tests automatisés, mais impossible à MONTRER — or c'est l'argument central de
 * l'architecture. Ce seeder permet d'ouvrir deux sessions côte à côte et de constater
 * qu'aucune donnée ne traverse (docs/MOBILE_API_READINESS.md §B9).
 *
 * Il sert aussi à vérifier qu'un même build mobile fonctionne pour deux entreprises
 * sans recompilation : branding, effectifs et incidents diffèrent, le code non.
 *
 * Idempotent : rejouable sans dupliquer.
 *
 *   php artisan db:seed --class=DemoTenantSeeder
 *
 * ATTENTION : mots de passe de démonstration en clair ci-dessous. Ce seeder est
 * réservé au développement et à la recette. Ne jamais l'exécuter en production.
 */
class DemoTenantSeeder extends Seeder
{
    private const SLUG = 'demo';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('DemoTenantSeeder est interdit en production.');

            return;
        }

        $tenant = Tenant::firstOrCreate(['slug' => self::SLUG], [
            'name'          => 'Demo Company',
            'short_name'    => 'DEMO',
            'status'        => 'active',
            'plan'          => 'business',
            'max_employees' => 500,
            // Couleur volontairement éloignée du bleu TCN : à l'écran, on voit
            // immédiatement sur quel tenant on est connecté.
            'primary_color' => '#7c3aed',
            'country'       => 'FR',
            'timezone'      => 'Europe/Paris',
            'locale'        => 'fr',
            'settings'      => [],
        ]);

        $users = [
            ['company_admin', 'DEMO-ADM-001', 'admin@demo-company.test',      'Amina Diallo'],
            ['hsse_manager',  'DEMO-HSE-001', 'hse.manager@demo-company.test', 'Karim Benali'],
            ['supervisor',    'DEMO-SUP-001', 'supervisor@demo-company.test',  'Lucie Moreau'],
            ['agent',         'DEMO-AGT-001', 'agent@demo-company.test',       'Ousmane Traore'],
        ];

        foreach ($users as [$role, $matricule, $email, $name]) {
            User::updateOrCreate(['matricule' => $matricule], [
                'name'      => $name,
                'email'     => $email,
                'password'  => Hash::make('Demo@2026'),
                'role'      => $role,
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]);
        }

        $admin = User::where('matricule', 'DEMO-ADM-001')->firstOrFail();

        // Le contexte tenant pilote l'auto-affectation de tenant_id (BelongsToTenant)
        // ET le global scope : sans lui, firstOrCreate ne verrait pas les lignes déjà
        // créées et en dupliquerait à chaque exécution.
        app(TenantContext::class)->set($tenant->id);

        try {
            $departments = collect([
                ['name' => 'Exploitation', 'code' => 'EXP'],
                ['name' => 'Maintenance',  'code' => 'MNT'],
                ['name' => 'QHSE',         'code' => 'QHSE'],
            ])->map(fn (array $d) => Department::firstOrCreate(['name' => $d['name']], $d));

            $employees = [
                ['DEMO-E-001', 'Sow',    'Fatimata', 'Cariste',              0],
                ['DEMO-E-002', 'Ndiaye', 'Ibrahima', 'Technicien',           1],
                ['DEMO-E-003', 'Leroy',  'Camille',  'Responsable QHSE',     2],
                ['DEMO-E-004', 'Barry',  'Aissatou', 'Operatrice',           0],
            ];

            foreach ($employees as [$matricule, $nom, $prenom, $poste, $deptIndex]) {
                Employee::firstOrCreate(['matricule' => $matricule], [
                    'nom'           => $nom,
                    'prenom'        => $prenom,
                    'poste'         => $poste,
                    'department_id' => $departments[$deptIndex]->id,
                    'type_contrat'  => 'CDI',
                    'date_embauche' => now()->subYears(2)->format('Y-m-d'),
                    'is_active'     => true,
                ]);
            }

            SafetyIncident::firstOrCreate(['reference' => 'INC-DEMO-0001'], [
                'date'        => now()->subDays(12)->format('Y-m-d'),
                'location'    => 'Atelier maintenance',
                'type'        => 'MTC',
                'severity'    => 'medium',
                'description' => 'Coupure a la main lors du remplacement dune piece.',
                'status'      => 'open',
                'reported_by' => $admin->id,
            ]);

            SafetyNearMiss::firstOrCreate(['reference' => 'NM-DEMO-0001'], [
                'date'                  => now()->subDays(5)->format('Y-m-d'),
                'location'              => 'Zone de chargement',
                'severity'              => 'high',
                'description'           => 'Palette instable sur un rayonnage en hauteur.',
                'potential_consequence' => 'Chute de charge sur zone de circulation pietonne.',
                'status'                => 'open',
                'reported_by'           => $admin->id,
            ]);

            EnvironmentReport::firstOrCreate(['reference' => 'ENV-DEMO-0001'], [
                'date'        => now()->subDays(3)->format('Y-m-d'),
                'location'    => 'Parc a dechets',
                'type'        => 'waste',
                'severity'    => 'low',
                'description' => 'Tri des dechets non conforme sur deux bennes.',
                'status'      => 'open',
                'reported_by' => $admin->id,
            ]);
        } finally {
            app(TenantContext::class)->clear();
        }

        $this->command->info('✓ Tenant « Demo Company » : 4 utilisateurs (1 par rôle), 3 départements, 4 employés, 3 signalements.');
        $this->command->line('  Connexion : DEMO-ADM-001 / DEMO-HSE-001 / DEMO-SUP-001 / DEMO-AGT-001 — PIN commun : Demo@2026');
    }
}
