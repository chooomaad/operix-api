<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Géolocalisation des évènements HSE.
 *
 * Trois tables, un même jeu de colonnes : incidents, presqu'accidents et rapports
 * environnementaux. Le champ `location` existant reste : il porte une désignation
 * humaine (« Quai 3 », « Atelier maintenance ») que des coordonnées ne remplacent
 * pas — on ne dit pas à une équipe d'intervention d'aller à 18.0735, -15.9582.
 *
 * TOUTES LES COLONNES SONT NULLABLES, délibérément. Un signalement sans position
 * est un signalement valide : GPS refusé, sous-sol, appareil sans capteur. Rendre
 * la position obligatoire pousserait à en fabriquer une, ce que le cahier des
 * charges interdit explicitement (§12 : « Ne jamais inventer une position »).
 *
 * `decimal(10,7)` : ±180,0000000, soit une précision d'environ 1 cm. Très au-delà
 * de ce qu'un GPS de téléphone fournit (3 à 10 m en conditions réelles), mais le
 * stockage décimal évite les erreurs d'arrondi d'un flottant sur des coordonnées.
 *
 * `location_accuracy` est le rayon d'incertitude en mètres tel que rapporté par
 * l'appareil. Le conserver permet de distinguer un point fiable d'une position
 * approximative déduite du réseau — deux choses qu'une carte ne doit pas afficher
 * de la même façon.
 *
 * `location_captured_at` est l'instant de la CAPTURE, distinct de `created_at` :
 * un signalement rédigé hors ligne puis synchronisé plus tard porte une position
 * prise bien avant son enregistrement.
 */
return new class extends Migration
{
    private const TABLES = [
        'safety_incidents',
        'safety_near_miss',
        'environment_reports',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->float('location_accuracy')->nullable();
                $t->timestamp('location_captured_at')->nullable();
            });

            // La carte filtre par tenant puis ne garde que les évènements situés.
            // L'index partiel ne couvre que ces lignes : inutile d'indexer les
            // signalements sans position, qui n'apparaîtront jamais sur la carte.
            DB::statement(
                "CREATE INDEX idx_{$table}_geo ON {$table} (tenant_id, latitude, longitude)"
                . ' WHERE latitude IS NOT NULL AND longitude IS NOT NULL'
            );

            // Cohérence : une latitude sans longitude ne désigne rien. La base
            // refuse une position à moitié renseignée, quelle que soit la couche
            // applicative qui l'écrit (API, import, seeder, console).
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$table}_geo_pair_check"
                . ' CHECK ((latitude IS NULL) = (longitude IS NULL))'
            );

            // Bornes géographiques. Une validation applicative peut être contournée
            // par un import ou une commande ; une contrainte ne l'est pas.
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$table}_geo_range_check"
                . ' CHECK ('
                . '  (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))'
                . '  AND (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))'
                . '  AND (location_accuracy IS NULL OR location_accuracy >= 0)'
                . ')'
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_geo_range_check");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_geo_pair_check");
            DB::statement("DROP INDEX IF EXISTS idx_{$table}_geo");

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn([
                    'latitude',
                    'longitude',
                    'location_accuracy',
                    'location_captured_at',
                ]);
            });
        }
    }
};
