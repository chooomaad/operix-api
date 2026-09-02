<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Champs de la fiche stagiaires TCN (import) : département, n° d'identité, durée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            if (! Schema::hasColumn('interns', 'departement'))     $table->string('departement')->nullable()->after('etablissement');
            if (! Schema::hasColumn('interns', 'numero_identite')) $table->string('numero_identite')->nullable()->after('departement');
            if (! Schema::hasColumn('interns', 'duree'))           $table->string('duree')->nullable()->after('numero_identite');
        });
    }

    public function down(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            foreach (['departement', 'numero_identite', 'duree'] as $c) {
                if (Schema::hasColumn('interns', $c)) $table->dropColumn($c);
            }
        });
    }
};
