<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Dommages matériels » (Property Damage) — 5e évènement HSSE.
 *
 * Calqué sur environment_reports : même forme (référence, date, lieu, type, gravité,
 * description, action corrective, statut, auteur), plus :
 *  - `estimated_cost` : coût estimé du dommage (indicateur métier propre à ce module) ;
 *  - `involved_people` (jsonb) : personnes impliquées, comme les 4 autres modules ;
 *  - géolocalisation et `report_file` (rapport PDF) alignés sur le reste du HSSE.
 *
 * tenant_id porté dès la création (table postérieure au backfill multi-tenant global),
 * avec unicité composite (tenant_id, reference) — jamais d'unicité globale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->date('date');
            $table->string('time')->nullable();
            $table->string('location');
            $table->enum('type', ['vehicle', 'equipment', 'infrastructure', 'cargo', 'container', 'other'])->default('other');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->text('immediate_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('corrective_action_due')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('involved_people')->nullable();
            $table->string('image')->nullable();
            $table->string('report_file')->nullable();

            // Géolocalisation — mêmes colonnes que les autres évènements HSSE.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('location_accuracy')->nullable();
            $table->timestamp('location_captured_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'date', 'type']);
            $table->index('status');
        });

        // Index GIN pour la recherche d'historique par personne (containment @>),
        // cohérent avec les 4 autres modules HSSE.
        DB::statement('CREATE INDEX IF NOT EXISTS property_damages_involved_people_gin ON property_damages USING GIN (involved_people)');

        // Index partiel + contraintes de cohérence géographique (identiques aux
        // autres évènements situés).
        DB::statement(
            'CREATE INDEX idx_property_damages_geo ON property_damages (tenant_id, latitude, longitude)'
            . ' WHERE latitude IS NOT NULL AND longitude IS NOT NULL'
        );
        DB::statement(
            'ALTER TABLE property_damages ADD CONSTRAINT property_damages_geo_pair_check'
            . ' CHECK ((latitude IS NULL) = (longitude IS NULL))'
        );
        DB::statement(
            'ALTER TABLE property_damages ADD CONSTRAINT property_damages_geo_range_check'
            . ' CHECK ('
            . '  (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))'
            . '  AND (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))'
            . '  AND (location_accuracy IS NULL OR location_accuracy >= 0)'
            . ')'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('property_damages');
    }
};
