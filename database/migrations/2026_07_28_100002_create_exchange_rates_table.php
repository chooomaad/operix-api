<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taux de change pour l'équivalence d'AFFICHAGE (ex. EUR → MRU).
 *
 * EUR reste la source de vérité commerciale ; ce taux ne sert qu'à afficher une
 * équivalence indicative (jamais à calculer un montant de commande faisant autorité).
 * Table dédiée pour permettre une mise à jour ultérieure (manuelle Super Admin ou job).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);   // 'EUR'
            $table->string('quote_currency', 3);  // 'MRU'
            $table->decimal('rate', 16, 6);        // 1 EUR = rate MRU
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
