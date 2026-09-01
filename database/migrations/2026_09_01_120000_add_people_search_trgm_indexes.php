<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accélère la recherche « personnes » (employee/contractor/visitor/intern) —
 * requêtes ILIKE '%terme%' sur nom/prénom/matricule/référence/entreprise.
 *
 * Sans index, un ILIKE '%x%' force un balayage séquentiel. L'extension pg_trgm
 * + des index GIN trigram rendent ces recherches « contient » rapides et stables
 * à mesure que les effectifs augmentent.
 *
 * Idempotent (IF NOT EXISTS) : sans effet si l'extension/les index existent déjà.
 * Si pg_trgm n'est pas disponible sur l'hôte, la migration échoue proprement au
 * CREATE EXTENSION — la recherche reste fonctionnelle (juste sans l'accélération).
 */
return new class extends Migration
{
    /** table => colonnes texte à indexer en trigram */
    private array $targets = [
        'employees'            => ['matricule', 'nom', 'prenom', 'poste'],
        'contractor_employees' => ['nom', 'prenom', 'badge_number'],
        'visitors'             => ['nom', 'prenom', 'badge_number', 'entreprise'],
        'interns'              => ['nom', 'prenom', 'reference', 'etablissement'],
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach ($this->targets as $table => $columns) {
            foreach ($columns as $col) {
                $idx = "{$table}_{$col}_trgm";
                DB::statement("CREATE INDEX IF NOT EXISTS {$idx} ON {$table} USING GIN ({$col} gin_trgm_ops)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $columns) {
            foreach ($columns as $col) {
                DB::statement("DROP INDEX IF EXISTS {$table}_{$col}_trgm");
            }
        }
    }
};
