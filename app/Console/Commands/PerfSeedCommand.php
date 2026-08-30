<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Sème un tenant de charge ISOLÉ pour les tests de performance (Phase E).
 *
 * Données dédiées, jamais celles d'un tenant réel. Idempotent : réexécuté, il
 * réutilise le tenant « LOADTEST » et complète les données manquantes. INTERDIT
 * en production (garde-fou).
 *
 * Comptes créés (PIN commun 739124) :
 *   LOAD-ADMIN  company_admin
 *   LOAD-HM     hsse_manager
 *   LOAD-AGENT  agent
 */
class PerfSeedCommand extends Command
{
    protected $signature = 'perf:seed {--incidents=200} {--employees=300}';
    protected $description = 'Seed an isolated load-test tenant (Phase E performance)';

    public function handle(TenantContext $context): int
    {
        if (app()->environment('production')) {
            $this->error('perf:seed est interdit en production.');
            return self::FAILURE;
        }

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'loadtest'],
            ['name' => 'LoadTest TCN', 'status' => 'active', 'plan' => 'enterprise', 'max_employees' => 1000],
        );
        $this->info("Tenant LOADTEST #{$tenant->id}");

        $roles = ['LOAD-ADMIN' => 'company_admin', 'LOAD-HM' => 'hsse_manager', 'LOAD-AGENT' => 'agent'];
        $users = [];
        foreach ($roles as $matricule => $role) {
            $user = User::firstOrCreate(
                ['matricule' => $matricule],
                [
                    'tenant_id' => $tenant->id,
                    'name'      => $matricule,
                    'email'     => strtolower($matricule) . '@loadtest.local',
                    'password'  => Hash::make('739124'),
                    'role'      => $role,
                    'is_active' => true,
                ],
            );
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
            $users[$role] = $user;
        }
        $this->info('Comptes: LOAD-ADMIN / LOAD-HM / LOAD-AGENT (PIN 739124)');

        $context->runWithoutScope(function () use ($context, $tenant, $users) {
            $context->set($tenant->id);
            try {
                $agent = $users['agent'];

                $haveEmp = Employee::where('tenant_id', $tenant->id)->count();
                $needEmp = max(0, (int) $this->option('employees') - $haveEmp);
                if ($needEmp > 0) {
                    $this->info("Seeding {$needEmp} employés…");
                    // Matricule garanti unique (contrainte composite tenant_id+matricule).
                    Employee::factory()->count($needEmp)
                        ->sequence(fn ($s) => ['matricule' => 'EMP-LT-' . $tenant->id . '-' . ($haveEmp + $s->index + 1)])
                        ->create(['tenant_id' => $tenant->id]);
                }

                $haveInc = SafetyIncident::where('tenant_id', $tenant->id)->count();
                $needInc = max(0, (int) $this->option('incidents') - $haveInc);
                if ($needInc > 0) {
                    $this->info("Seeding {$needInc} incidents…");
                    SafetyIncident::factory()->count($needInc)->create([
                        'tenant_id'   => $tenant->id,
                        'reported_by' => $agent->id,
                    ]);
                }
            } finally {
                $context->clear();
            }
        });

        $this->info('OK. Incidents=' . SafetyIncident::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()
            . ' Employés=' . Employee::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        return self::SUCCESS;
    }
}
