<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance supplémentaires.
 * Les index de base (sur date, status, employee_id, etc.) sont déjà
 * définis dans les migrations de création de chaque table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index full-text sur les champs de recherche fréquents
        Schema::table('employees', function (Blueprint $table) {
            $table->index('poste', 'idx_employees_poste');
        });

        Schema::table('safety_incidents', function (Blueprint $table) {
            $table->index('reported_by', 'idx_incidents_reporter');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_employees_poste');
        });
        Schema::table('safety_incidents', function (Blueprint $table) {
            $table->dropIndex('idx_incidents_reporter');
        });
    }
};
