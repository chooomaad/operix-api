<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire la contrainte CHECK sur tenants.plan.
 *
 * tenants.plan est un CACHE dénormalisé du slug de plan. Les plans étant dynamiques
 * (administrables depuis le Super Admin), contraindre ce cache à 3 valeurs fixes est
 * incorrect. La SOURCE DE VÉRITÉ reste subscriptions.plan_id ; tenants.plan reflète
 * simplement le slug courant (chaîne libre, nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_plan_check');
    }

    public function down(): void
    {
        // Restaure une contrainte cohérente avec la nomenclature commerciale actuelle.
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_plan_check CHECK (plan::text = ANY (ARRAY['starter','business','enterprise']::text[]))");
    }
};
