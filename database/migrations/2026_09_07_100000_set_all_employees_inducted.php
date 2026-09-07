<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marque TOUS les employés existants comme ayant suivi l'induction
 * (induction_status = true) — demande métier : le personnel déjà en poste est
 * réputé inducté.
 *
 * Data-migration non destructive : ne touche qu'un booléen, uniquement les lignes
 * présentes au moment de la migration. Les employés créés ensuite conservent le
 * comportement normal (induction à renseigner). `down` volontairement no-op : on ne
 * peut pas restaurer un état d'induction antérieur inconnu.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')->whereNull('deleted_at')->update(['induction_status' => true]);
    }

    public function down(): void
    {
        // No-op : pas de restauration de l'état d'induction précédent.
    }
};
