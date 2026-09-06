<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module de management des risques (SMS HSSE).
 *
 *  - `risks` : registre des risques + évaluation par matrice 5×5. Le score
 *    (probabilité × gravité) et le niveau sont RECALCULÉS côté serveur (modèle
 *    Risk) à chaque écriture — aucune couche cliente ne peut les falsifier. Le
 *    risque résiduel (après mesures de contrôle) suit la même règle.
 *  - `risk_actions` : plan d'action rattaché à un risque. Le « retard » n'est pas
 *    un statut stocké mais une propriété dérivée (échéance dépassée + non terminé).
 *
 * Contraintes CHECK : probabilité/gravité bornées à 1..5 quelle que soit la couche
 * qui écrit (API, import, console).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();               // RM-2026-0001

            // ── Registre ──────────────────────────────────────────────────────
            $table->date('date_identification');
            $table->string('location');                            // site / zone / département
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('activity')->nullable();                // activité / tâche
            $table->string('category');                            // type de risque (voir Risk::CATEGORIES)
            $table->string('assessment_type')->default('risk_assessment'); // jsa | hira | risk_assessment
            $table->text('danger');                                // danger identifié
            $table->text('risk_description');                      // risque associé
            $table->text('causes')->nullable();
            $table->text('consequences')->nullable();
            $table->text('exposed_persons')->nullable();           // personnes exposées
            $table->text('existing_controls')->nullable();         // mesures de contrôle existantes
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // responsable du risque

            // ── Évaluation initiale (matrice 5×5) ─────────────────────────────
            $table->unsignedTinyInteger('probability');            // 1..5
            $table->unsignedTinyInteger('severity');               // 1..5
            $table->unsignedTinyInteger('score')->default(1);      // = probability × severity (calculé)
            $table->string('level')->default('low');               // low|medium|high|critical (calculé)

            // ── Hiérarchie des mesures de contrôle ────────────────────────────
            // [{ "hierarchy": "elimination|substitution|engineering|administrative|ppe", "description": "..." }]
            $table->jsonb('controls')->nullable();

            // ── Évaluation résiduelle (après mesures) ─────────────────────────
            $table->unsignedTinyInteger('residual_probability')->nullable();
            $table->unsignedTinyInteger('residual_severity')->nullable();
            $table->unsignedTinyInteger('residual_score')->nullable();
            $table->string('residual_level')->nullable();

            $table->string('status')->default('open');             // open | monitoring | closed
            $table->date('review_date')->nullable();               // date de ré-évaluation
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'level']);
            $table->index(['tenant_id', 'status']);
            $table->index('review_date');
        });

        DB::statement('ALTER TABLE risks ADD CONSTRAINT risks_prob_range CHECK (probability BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risks ADD CONSTRAINT risks_sev_range CHECK (severity BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risks ADD CONSTRAINT risks_res_prob_range CHECK (residual_probability IS NULL OR residual_probability BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risks ADD CONSTRAINT risks_res_sev_range CHECK (residual_severity IS NULL OR residual_severity BETWEEN 1 AND 5)');

        Schema::create('risk_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained('risks')->cascadeOnDelete();
            $table->text('description');
            $table->string('type')->nullable();                    // corrective | preventive
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('priority')->default('medium');         // low | medium | high
            $table->string('status')->default('todo');             // todo | in_progress | done
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('proof')->nullable();                   // preuve / photo / document
            $table->date('closed_at')->nullable();                 // date de clôture
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete(); // validation HSE
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'risk_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_actions');
        Schema::dropIfExists('risks');
    }
};
