<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Les champs TCN spécifiques (nni, section, entreprise, induction_status, etc.)
 * sont désormais inclus directement dans les migrations de création de tables.
 * Ce fichier est conservé pour maintenir l'ordre de migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
