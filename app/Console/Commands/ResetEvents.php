<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remise à zéro des DONNÉES D'ÉVÉNEMENTS avant le démarrage réel.
 *
 * Vide uniquement les tables métier/événements et d'historique (incidents, near
 * miss, breaches, environnement, formations, certifications, visites médicales,
 * journal d'audit, notifications, jetons transitoires).
 *
 * NE TOUCHE JAMAIS aux identités et à la configuration : utilisateurs, employés,
 * tenants, rôles, permissions, départements, organisation, prestataires, plans…
 * Ces tables ne figurent PAS dans $eventTables et sont donc intactes.
 */
class ResetEvents extends Command
{
    protected $signature = 'operix:reset-events {--force : Exécute sans confirmation}';

    protected $description = 'Vide les données d\'événements/historique de test (préserve comptes, employés, config).';

    /** Tables d'événements / historique à vider. */
    private array $eventTables = [
        'safety_incidents',
        'safety_near_miss',
        'breaches',
        'environment_reports',
        'formations',
        'certifications',
        'medical_visits',
        'gemba_walks',
        'equipment_inspections',
        'activity_logs',
        'notifications',
        'pin_reset_tokens',
        'otp_tokens',
    ];

    /** Tables explicitement PRÉSERVÉES (documentaire — jamais vidées). */
    private array $preserved = [
        'users', 'employees', 'tenants', 'roles', 'permissions', 'departments',
        'organisation', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'contractors', 'contractor_employees', 'equipment', 'visitors',
        'plans', 'exchange_rates', 'orders', 'payments', 'subscriptions',
        'demo_requests', 'tenant_activations',
    ];

    public function handle(): int
    {
        $this->info('Tables d\'événements ciblées (seront VIDÉES) :');
        $before = [];
        foreach ($this->eventTables as $t) {
            if (! Schema::hasTable($t)) { continue; }
            $before[$t] = DB::table($t)->count();
            $this->line(sprintf('  %-24s %d ligne(s)', $t, $before[$t]));
        }

        $this->newLine();
        $this->info('Tables PRÉSERVÉES (intactes) : ' . implode(', ', array_slice($this->preserved, 0, 8)) . ', …');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Confirmer la suppression de TOUTES ces données d\'événements ?')) {
            $this->warn('Annulé. Aucune donnée supprimée.');
            return self::FAILURE;
        }

        DB::transaction(function () {
            foreach ($this->eventTables as $t) {
                if (! Schema::hasTable($t)) { continue; }
                DB::table($t)->delete();
                // Redémarre la séquence d'id pour un départ propre (INC-2026-0001…).
                // `IF EXISTS` : ne casse pas la transaction si la séquence est absente
                // (clé UUID, etc.) — sous PostgreSQL une erreur avortée poisonne tout le bloc.
                DB::statement("ALTER SEQUENCE IF EXISTS {$t}_id_seq RESTART WITH 1");
            }
        });

        $this->newLine();
        $this->info('✔ Nettoyage terminé. Vérification :');
        foreach ($this->eventTables as $t) {
            if (! Schema::hasTable($t)) { continue; }
            $this->line(sprintf('  %-24s %d ligne(s)', $t, DB::table($t)->count()));
        }

        // Contrôle d'intégrité : comptes et employés préservés.
        $this->newLine();
        $this->info(sprintf(
            'Intégrité : users=%d, employees=%d, tenants=%d, roles=%d, permissions=%d (préservés).',
            DB::table('users')->count(),
            DB::table('employees')->count(),
            DB::table('tenants')->count(),
            DB::table('roles')->count(),
            DB::table('permissions')->count(),
        ));

        return self::SUCCESS;
    }
}
