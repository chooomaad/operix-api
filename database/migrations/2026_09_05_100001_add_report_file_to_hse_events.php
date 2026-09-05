<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute un champ `report_file` (rapport PDF joint) aux évènements HSSE existants.
 *
 * Chaque incident, presqu'accident, manquement et rapport environnemental peut
 * désormais porter un rapport formel (PDF) en pièce jointe, en plus de la photo
 * (`image`). Le fichier est stocké sur le disque privé du tenant et servi par URL
 * signée, comme les autres médias.
 *
 * ADDITIF & non destructif : simple colonne nullable, aucune donnée touchée.
 */
return new class extends Migration
{
    private array $tables = ['safety_incidents', 'safety_near_miss', 'breaches', 'environment_reports'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'report_file')) {
                    $table->string('report_file')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'report_file')) {
                    $table->dropColumn('report_file');
                }
            });
        }
    }
};
