<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Formation;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportTcnData extends Command
{
    protected $signature = 'import:tcn
                            {--module= : Module à importer (users,employees,safety,formations,all)}
                            {--dry-run : Simulation sans écriture en base}';

    protected $description = 'Importe les données MySQL TCN-CONTENEUR vers PostgreSQL Operix';

    private \PDO $mysql;
    private bool $dryRun;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $module       = $this->option('module') ?? 'all';

        $this->info('Import TCN → Operix' . ($this->dryRun ? ' [DRY-RUN]' : ''));
        $this->newLine();

        $this->mysql = $this->mysqlConnection();

        match ($module) {
            'users'      => $this->importUsers(),
            'employees'  => $this->importEmployees(),
            'safety'     => $this->importSafety(),
            'formations' => $this->importFormations(),
            'all'        => $this->importAll(),
            default      => $this->error("Module inconnu : {$module}"),
        };

        $this->newLine();
        $this->info('Import terminé.');
        return self::SUCCESS;
    }

    private function importAll(): void
    {
        $this->importUsers();
        $this->importEmployees();
        $this->importSafety();
        $this->importFormations();
    }

    // ── Users ──────────────────────────────────────────────────────────────────
    private function importUsers(): void
    {
        $this->line('<fg=cyan>── Utilisateurs</fg=cyan>');
        $rows = $this->mysql->query("SELECT * FROM users")->fetchAll(\PDO::FETCH_ASSOC);

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $matricule = $row['matricule'] ?? '';
            $email     = $row['email'] ?? ($matricule . '@tcn.local');

            if (!$this->dryRun) {
                $existing = User::where('matricule', $matricule)->first();
                if ($existing) {
                    $existing->update(['password' => $row['pin'] ?? Hash::make('0000')]);
                    $skipped++;
                    continue;
                }

                $emailFinal = User::where('email', $email)->exists()
                    ? ($matricule . '@tcn.local')
                    : $email;

                User::create([
                    'name'      => trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? $matricule)) ?: $matricule,
                    'email'     => $emailFinal,
                    'password'  => $row['pin'] ?? Hash::make('0000'),
                    'role'      => $row['role'] === 'admin' ? 'admin' : 'agent',
                    'matricule' => $matricule,
                    'is_active' => true,
                ]);
            }
            $created++;
        }

        $this->line("  Créés : {$created} | Ignorés (existants) : {$skipped}");
    }

    // ── Employees ──────────────────────────────────────────────────────────────
    private function importEmployees(): void
    {
        $this->line('<fg=cyan>── Employés</fg=cyan>');

        $rows = $this->mysql
            ->query("SELECT * FROM employees WHERE type = 'employee' ORDER BY id")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $matricule = $row['matricule'] ?? '';

            if ($matricule && Employee::where('matricule', $matricule)->exists()) {
                $skipped++;
                continue;
            }

            if (!$this->dryRun) {
                Employee::create([
                    'matricule'            => $matricule ?: null,
                    'nni'                  => $row['nni'] ?? null,
                    'nom'                  => $row['nom'],
                    'prenom'               => $row['prenom'],
                    'email'                => $row['email'] ?? null,
                    'phone'                => $row['telephone'] ?? null,
                    'poste'                => $row['poste'] ?? 'Non défini',
                    'section'              => $row['section'] ?? null,
                    'type_contrat'         => 'CDI',
                    'date_embauche'        => $row['date_embauche'] ?: null,
                    'photo'                => $row['photo'] ?? null,
                    'is_active'            => true,
                    'induction_status'     => isset($row['induction_status']) ? (bool) $row['induction_status'] : null,
                    'induction_start_date' => $row['induction_start_date'] ?: null,
                    'last_modified_by'     => $row['last_modified_by'] ?? null,
                    'last_modified_at'     => $row['last_modified_at'] ?: null,
                ]);
            }
            $created++;
        }

        $this->line("  Employés créés : {$created} | Ignorés : {$skipped}");
    }

    // ── Incidents & Near Miss ──────────────────────────────────────────────────
    private function importSafety(): void
    {
        $this->line('<fg=cyan>── Incidents</fg=cyan>');

        $typeMap = [
            'LTI' => 'LTI', 'MTC' => 'MTC', 'RWC' => 'RWC',
            'FIRE' => 'Fire', 'FIRST_AID' => 'FAC', 'FAC' => 'FAC',
            'HPI' => 'HPI', 'SECURITY' => 'Security',
        ];
        $sevMap = ['high' => 'high', 'medium' => 'medium', 'low' => 'low', 'critical' => 'critical'];

        $rows    = $this->mysql->query("SELECT * FROM safety_incidents ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $created = 0;

        foreach ($rows as $row) {
            if (SafetyIncident::where('reference', $row['reference'] ?? '')->exists()) {
                continue;
            }

            if (!$this->dryRun) {
                SafetyIncident::create([
                    'reference'         => $row['reference'] ?? ('INC-' . date('Y') . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)),
                    'date'              => $row['date'] ?: now()->toDateString(),
                    'time'              => $row['time'] ?? null,
                    'location'          => $row['location'] ?? 'Inconnu',
                    'type'              => $typeMap[strtoupper($row['type'] ?? '')] ?? 'Autre',
                    'severity'          => $sevMap[$row['severity'] ?? ''] ?? 'low',
                    'description'       => $row['description'] ?? '',
                    'status'            => $row['status'] ?? 'open',
                    'immediate_cause'   => $row['immediate_cause'] ?? null,
                    'root_cause'        => $row['root_cause'] ?? null,
                    'corrective_action' => $row['corrective_action'] ?? null,
                    'reported_by'       => 1,
                ]);
            }
            $created++;
        }
        $this->line("  Incidents créés : {$created}");

        $this->line('<fg=cyan>── Near Miss</fg=cyan>');
        $nmRows    = $this->mysql->query("SELECT * FROM safety_near_miss ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $nmCreated = 0;

        foreach ($nmRows as $row) {
            if (SafetyNearMiss::where('reference', $row['reference'] ?? '')->exists()) {
                continue;
            }
            if (!$this->dryRun) {
                SafetyNearMiss::create([
                    'reference'   => $row['reference'] ?? ('NM-' . date('Y') . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)),
                    'date'        => $row['date'] ?: now()->toDateString(),
                    'location'    => $row['location'] ?? 'Inconnu',
                    'type'        => $row['type'] ?? 'Autre',
                    'description' => $row['description'] ?? '',
                    'status'      => $row['status'] ?? 'open',
                    'reported_by' => 1,
                ]);
            }
            $nmCreated++;
        }
        $this->line("  Near Miss créés : {$nmCreated}");
    }

    // ── Formations ─────────────────────────────────────────────────────────────
    private function importFormations(): void
    {
        $this->line('<fg=cyan>── Formations</fg=cyan>');

        $rows = $this->mysql->query("
            SELECT f.*, e.matricule
            FROM formations f
            JOIN employees e ON e.id = f.employee_id
            WHERE e.type = 'employee'
            ORDER BY f.id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $created = 0;
        foreach ($rows as $row) {
            $emp = Employee::where('matricule', $row['matricule'])->first();
            if (!$emp) continue;

            if (!$this->dryRun) {
                Formation::create([
                    'employee_id' => $emp->id,
                    'titre'       => $row['titre'] ?? $row['formation'] ?? 'Formation',
                    'organisme'   => $row['organisme'] ?? null,
                    'date_debut'  => $row['date_debut'] ?? $row['date'] ?? now()->toDateString(),
                    'date_fin'    => $row['date_fin'] ?? null,
                    'statut'      => $row['statut'] ?? 'complétée',
                    'observations' => $row['remarques'] ?? null,
                ]);
            }
            $created++;
        }
        $this->line("  Formations créées : {$created}");
    }

    // ── Connexion MySQL ────────────────────────────────────────────────────────
    private function mysqlConnection(): \PDO
    {
        $host = env('TCN_DB_HOST', '127.0.0.1');
        $db   = env('TCN_DB_NAME', 'tcn_conteneur');
        $user = env('TCN_DB_USER', 'root');
        $pass = env('TCN_DB_PASS', '');

        return new \PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
        );
    }
}
