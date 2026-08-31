<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne le module « Breaches » (infractions) sur le modele HSSE des autres
 * evenements : plusieurs personnes impliquees (colonne jsonb `employees`, comme
 * incidents/near-miss) et gravite HSSE (low/medium/high/critical) au lieu de
 * l'ancienne notion disciplinaire (avertissement/blame…). `employee_id` reste
 * present (nullable) pour compat, mais n'est plus obligatoire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breaches', function (Blueprint $table) {
            if (! Schema::hasColumn('breaches', 'employees')) {
                $table->jsonb('employees')->default('[]')->after('employee_id');
            }
        });

        // La gravite passe d'un enum disciplinaire a une chaine HSSE. Sur Postgres,
        // enum() = varchar + contrainte CHECK : on retire la contrainte et on
        // repositionne un defaut coherent.
        DB::statement("ALTER TABLE breaches DROP CONSTRAINT IF EXISTS breaches_severity_check");
        DB::statement("ALTER TABLE breaches ALTER COLUMN severity TYPE varchar(20)");
        DB::statement("ALTER TABLE breaches ALTER COLUMN severity SET DEFAULT 'medium'");
    }

    public function down(): void
    {
        Schema::table('breaches', function (Blueprint $table) {
            if (Schema::hasColumn('breaches', 'employees')) {
                $table->dropColumn('employees');
            }
        });
        DB::statement("ALTER TABLE breaches ALTER COLUMN severity SET DEFAULT 'avertissement'");
    }
};
