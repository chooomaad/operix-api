<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Réconciliation Tenant.plan avec le référentiel commercial (Starter/Business/Enterprise).
 *
 * L'ancien enum autorisait 'pro' ; la nouvelle nomenclature utilise 'business'.
 * On migre proprement les données existantes (pro → business) et on met à jour la
 * contrainte CHECK, sans perte de données. Tenant.plan reste un CACHE dénormalisé ;
 * la source de vérité est subscriptions.plan_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_plan_check');
        DB::table('tenants')->where('plan', 'pro')->update(['plan' => 'business']);
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_plan_check CHECK (plan::text = ANY (ARRAY['starter','business','enterprise']::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_plan_check');
        DB::table('tenants')->where('plan', 'business')->update(['plan' => 'pro']);
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_plan_check CHECK (plan::text = ANY (ARRAY['starter','pro','enterprise']::text[]))");
    }
};
