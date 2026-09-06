<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte de la dotation EPI : une remise peut porter PLUSIEURS articles et
 * PLUSIEURS catégories à la fois. La quantité devient dérivée (nombre de
 * catégories) et le champ « taille » est retiré.
 *
 *  - `items`      (jsonb) : liste d'articles EPI sélectionnés.
 *  - `categories` (jsonb) : liste de catégories (tête, yeux, mains…).
 *  - `size`               : supprimé.
 *  - `designation`        : conservé mais rendu nullable (héritage, non utilisé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppe_issuances', function (Blueprint $table) {
            if (Schema::hasColumn('ppe_issuances', 'size')) {
                $table->dropColumn('size');
            }
            if (! Schema::hasColumn('ppe_issuances', 'items')) {
                $table->jsonb('items')->nullable();
            }
            if (! Schema::hasColumn('ppe_issuances', 'categories')) {
                $table->jsonb('categories')->nullable();
            }
        });

        // designation n'est plus obligatoire (remplacé par items).
        DB::statement('ALTER TABLE ppe_issuances ALTER COLUMN designation DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('ppe_issuances', function (Blueprint $table) {
            if (! Schema::hasColumn('ppe_issuances', 'size')) {
                $table->string('size')->nullable();
            }
            if (Schema::hasColumn('ppe_issuances', 'items')) {
                $table->dropColumn('items');
            }
            if (Schema::hasColumn('ppe_issuances', 'categories')) {
                $table->dropColumn('categories');
            }
        });
    }
};
