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
    protected $signature = 'operix:reset-events {--force : Exécute sans confirmation} {--with-people : Vide AUSSI employés/sous-traitants/visiteurs/stagiaires (mise en production)}';

    protected $description = 'Vide les données d\'événements/historique de test. --with-people vide aussi les personnes (préserve TOUJOURS comptes users, rôles, permissions, départements, config).';

    /** Tables « personnes » (vidées seulement avec --with-people). Ordre = FK. */
    private array $peopleTables = [
        'contractor_employees', 'contractors', 'visitors', 'interns', 'employees',
    ];

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
        $withPeople = (bool) $this->option('with-people');
        $targets = $withPeople ? array_merge($this->eventTables, $this->peopleTables) : $this->eventTables;

        $this->info($withPeople
            ? 'MISE EN PRODUCTION — tables ÉVÉNEMENTS + PERSONNES ciblées (seront VIDÉES) :'
            : 'Tables d\'événements ciblées (seront VIDÉES) :');
        foreach ($targets as $t) {
            if (! Schema::hasTable($t)) { continue; }
            $this->line(sprintf('  %-24s %d ligne(s)', $t, DB::table($t)->count()));
        }

        $this->newLine();
        $this->info('TOUJOURS PRÉSERVÉ : users (comptes), roles, permissions, departments, organisation, tenants.');
        if ($withPeople) {
            $this->warn('⚠ --with-people : employés/sous-traitants/visiteurs/stagiaires SERONT SUPPRIMÉS.');
        }
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Confirmer la suppression de TOUTES ces données ?')) {
            $this->warn('Annulé. Aucune donnée supprimée.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($targets) {
            foreach ($targets as $t) {
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
        foreach ($targets as $t) {
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
