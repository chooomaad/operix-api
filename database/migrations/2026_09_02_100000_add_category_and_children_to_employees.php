<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes officielles de la liste employés TCN absentes du modèle :
 *  - code catégorie (category_code)
 *  - nombre d'enfants (nombre_enfants)
 *
 * Additif & non destructif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'category_code')) {
                $table->string('category_code')->nullable()->after('type_contrat');
            }
            if (! Schema::hasColumn('employees', 'nombre_enfants')) {
                $table->unsignedSmallInteger('nombre_enfants')->nullable()->after('category_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'category_code'))  $table->dropColumn('category_code');
            if (Schema::hasColumn('employees', 'nombre_enfants')) $table->dropColumn('nombre_enfants');
        });
    }
};
