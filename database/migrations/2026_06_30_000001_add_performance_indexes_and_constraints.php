<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contraintes de validation supplémentaires (PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Vide — les index sont gérés dans les migrations de création.
        // Fichier conservé pour maintenir l'ordre de migration.
    }

    public function down(): void
    {
        //
    }
};
