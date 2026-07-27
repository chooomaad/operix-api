<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durcissement de l'isolation au niveau base :
 *  1. Unicités GLOBALES → composites UNIQUE(tenant_id, col) : deux entreprises peuvent
 *     réutiliser les mêmes références/matricules/codes (règle métier multi-tenant).
 *  2. FK tenant_id → tenants (cascade) sur les tables métier, nullOnDelete sur users.
 *  3. tenant_id NOT NULL sur les tables métier (users reste nullable : super_admin).
 *
 * S'exécute APRÈS le backfill (commit 5) : en dev/prod toutes les lignes ont déjà un
 * tenant_id ; en base de test les tables sont vides à la migration (RefreshDatabase).
 */
return new class extends Migration
{
    /** Colonnes uniques globales à convertir en composites (table => colonne). */
    private array $composites = [
        'safety_incidents'    => 'reference',
        'gemba_walks'         => 'reference',
        'safety_near_miss'    => 'reference',
        'environment_reports' => 'reference',
        'breaches'            => 'reference',
        'permit_to_work'      => 'reference',
        'departments'         => 'name',
        'employees'           => 'matricule',
        'equipment'           => 'code',
    ];

    /** Tables métier recevant FK + NOT NULL. */
    private array $businessTables = [
        'departments', 'employees', 'safety_incidents', 'safety_near_miss',
        'environment_reports', 'gemba_walks', 'breaches', 'formations',
        'certifications', 'medical_visits', 'visitors', 'contractors',
        'contractor_employees', 'permit_to_work', 'equipment',
        'equipment_inspections', 'media', 'notifications', 'activity_logs',
    ];

    public function up(): void
    {
        foreach ($this->composites as $table => $col) {
            Schema::table($table, function (Blueprint $t) use ($table, $col) {
                $t->dropUnique("{$table}_{$col}_unique");
                $t->unique(['tenant_id', $col]);
            });
        }

        // employees.email est aussi unique global.
        Schema::table('employees', function (Blueprint $t) {
            $t->dropUnique('employees_email_unique');
            $t->unique(['tenant_id', 'email']);
        });

        foreach ($this->businessTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        // users : tenant_id reste nullable (super_admin plateforme), FK nullOnDelete.
        Schema::table('users', function (Blueprint $t) {
            $t->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['tenant_id']);
        });

        foreach ($this->businessTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['tenant_id']);
                $t->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        Schema::table('employees', function (Blueprint $t) {
            $t->dropUnique(['tenant_id', 'email']);
            $t->unique('email');
        });

        foreach ($this->composites as $table => $col) {
            Schema::table($table, function (Blueprint $t) use ($col) {
                $t->dropUnique(['tenant_id', $col]);
                $t->unique($col);
            });
        }
    }
};
