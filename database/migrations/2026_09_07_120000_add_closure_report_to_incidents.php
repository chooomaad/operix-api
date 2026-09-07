<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapport de clôture d'un incident : fichier PDF OBLIGATOIRE au moment de fermer
 * l'incident (preuve documentaire de la clôture), distinct d'un éventuel rapport
 * joint à la création (`report_file`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safety_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('safety_incidents', 'closure_report')) {
                $table->string('closure_report')->nullable()->after('report_file');
            }
        });
    }

    public function down(): void
    {
        Schema::table('safety_incidents', function (Blueprint $table) {
            if (Schema::hasColumn('safety_incidents', 'closure_report')) {
                $table->dropColumn('closure_report');
            }
        });
    }
};
