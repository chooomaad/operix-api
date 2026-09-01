<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend les dossiers RH (formations, certifications, visites médicales) rattachables
 * à N'IMPORTE QUELLE personne (employee/contractor/visitor/intern), et plus seulement
 * à un employé.
 *
 * ADDITIF & non destructif : on AJOUTE person_type + person_id. L'employee_id
 * existant reste intact (le profil employé continue de fonctionner tel quel). Les
 * profils des autres types utilisent (person_type, person_id).
 */
return new class extends Migration
{
    private array $tables = ['formations', 'certifications', 'medical_visits'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'person_type')) {
                    $table->string('person_type')->nullable()->index();
                }
                if (! Schema::hasColumn($t, 'person_id')) {
                    $table->unsignedBigInteger('person_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'person_type')) $table->dropColumn('person_type');
                if (Schema::hasColumn($t, 'person_id'))   $table->dropColumn('person_id');
            });
        }
    }
};
