<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centralisation des « personnes » impliquables dans les événements HSSE.
 *
 *  1. Nouvelle table `interns` (stagiaires) — type de personne à part entière.
 *  2. Les 4 modules HSSE : la colonne jsonb `employees` (tableau d'ids employé)
 *     devient `involved_people`, destinée à stocker `[{ "type": ..., "id": ... }]`
 *     (employee | contractor | visitor | intern). Un index GIN accélère la
 *     recherche d'historique par personne (containment @>).
 *
 * NOTE : au moment de cette migration ces colonnes jsonb sont VIDES (base remise
 * à zéro), donc le renommage/format ne perd aucune donnée réelle. Un back-fill de
 * sécurité convertit malgré tout tout `[id, …]` legacy en `[{type:employee,id}]`.
 */
return new class extends Migration
{
    private array $hsseTables = ['safety_incidents', 'safety_near_miss', 'breaches', 'environment_reports'];

    public function up(): void
    {
        // ── 1. Table interns ────────────────────────────────────────────────
        if (! Schema::hasTable('interns')) {
            Schema::create('interns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('reference')->nullable();        // INT-2026-0001
                $table->string('nom');
                $table->string('prenom');
                $table->string('etablissement')->nullable();    // école / université
                $table->string('encadrant')->nullable();        // tuteur interne
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->date('date_debut')->nullable();
                $table->date('date_fin')->nullable();
                $table->string('status')->default('active');    // active | ended
                $table->boolean('is_active')->default(true);
                $table->string('photo')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'is_active']);
                $table->index('reference');
            });
        }

        // ── 2. involved_people (rename + GIN) sur les 4 modules ─────────────
        foreach ($this->hsseTables as $t) {
            if (Schema::hasColumn($t, 'employees') && ! Schema::hasColumn($t, 'involved_people')) {
                Schema::table($t, fn (Blueprint $table) => $table->renameColumn('employees', 'involved_people'));
            } elseif (! Schema::hasColumn($t, 'involved_people')) {
                Schema::table($t, fn (Blueprint $table) => $table->jsonb('involved_people')->nullable());
            }

            // Back-fill : legacy [id,…] -> [{"type":"employee","id":id},…]
            DB::statement("
                UPDATE {$t}
                SET involved_people = (
                    SELECT jsonb_agg(jsonb_build_object('type','employee','id', elem))
                    FROM jsonb_array_elements(involved_people) AS elem
                )
                WHERE involved_people IS NOT NULL
                  AND jsonb_typeof(involved_people) = 'array'
                  AND jsonb_array_length(involved_people) > 0
                  AND jsonb_typeof(involved_people->0) = 'number'
            ");

            // Index GIN pour la recherche par containment (@>)
            $idx = "{$t}_involved_people_gin";
            DB::statement("CREATE INDEX IF NOT EXISTS {$idx} ON {$t} USING GIN (involved_people)");
        }
    }

    public function down(): void
    {
        foreach ($this->hsseTables as $t) {
            DB::statement("DROP INDEX IF EXISTS {$t}_involved_people_gin");
            if (Schema::hasColumn($t, 'involved_people') && ! Schema::hasColumn($t, 'employees')) {
                Schema::table($t, fn (Blueprint $table) => $table->renameColumn('involved_people', 'employees'));
            }
        }
        Schema::dropIfExists('interns');
    }
};
