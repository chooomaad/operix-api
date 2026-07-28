<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration : rattache les données TCN existantes au Tenant TCN.
 *
 * Idempotente et pilotée par les données :
 *  - base vierge (ex. base de test) → aucune donnée → no-op (les tests créent leurs
 *    propres tenants via factory) ;
 *  - dev/prod avec données existantes → crée le tenant TCN depuis `organisation`,
 *    puis backfill toutes les lignes métier et les utilisateurs (sauf super_admin).
 *
 * La FK + NOT NULL sont posés APRÈS ce backfill (commit 6).
 */
return new class extends Migration
{
    /** @var string[] Tables métier à rattacher (mêmes que la migration tenant_id). */
    private array $tables = [
        'departments', 'employees', 'safety_incidents', 'safety_near_miss',
        'environment_reports', 'gemba_walks', 'breaches', 'formations',
        'certifications', 'medical_visits', 'visitors', 'contractors',
        'contractor_employees', 'permit_to_work', 'equipment',
        'equipment_inspections', 'media', 'notifications', 'activity_logs',
    ];

    public function up(): void
    {
        $org = DB::table('organisation')->first();

        $hasData = $org !== null
            || DB::table('users')->exists()
            || DB::table('employees')->exists();

        if (! $hasData) {
            return; // Base vierge : rien à backfiller.
        }

        // 1. Tenant TCN (créé une seule fois, depuis le branding organisation).
        $tenantId = DB::table('tenants')->where('slug', 'tcn')->value('id');
        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name'          => $org->name          ?? 'Terminal à Conteneurs de Nouakchott',
                'short_name'    => $org->short_name     ?? 'TCN',
                'slug'          => 'tcn',
                'status'        => 'active',
                'plan'          => 'enterprise',
                'max_employees' => 100000,
                'logo'          => $org->logo           ?? null,
                'primary_color' => $org->primary_color  ?? '#0f2847',
                'country'       => $org->country        ?? 'MR',
                'timezone'      => $org->timezone       ?? 'Africa/Nouakchott',
                'locale'        => $org->locale         ?? 'fr',
                'settings'      => $org->settings       ?? '{}',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // 2. Backfill des tables métier.
        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }

        // 3. Utilisateurs existants → TCN, SAUF super_admin (rôle plateforme, reste NULL).
        DB::table('users')
            ->whereNull('tenant_id')
            ->where('role', '!=', 'super_admin')
            ->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        $tenantId = DB::table('tenants')->where('slug', 'tcn')->value('id');
        if (! $tenantId) {
            return;
        }

        foreach ($this->tables as $table) {
            DB::table($table)->where('tenant_id', $tenantId)->update(['tenant_id' => null]);
        }
        DB::table('users')->where('tenant_id', $tenantId)->update(['tenant_id' => null]);
        DB::table('tenants')->where('id', $tenantId)->delete();
    }
};
