<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans commerciaux Operix (Starter / Business / Enterprise).
 *
 * - Table GLOBALE (plateforme) : aucun tenant_id (les plans ne sont pas des données tenant).
 * - Prix stockés en EUR, en UNITÉS MINEURES (centimes) → entiers, jamais de flottants.
 *   EUR = source de vérité commerciale. L'équivalence MRU est calculée à l'affichage
 *   via exchange_rates (jamais hardcodée côté client).
 * - Enterprise : prix nullable + contact_sales = true (pas de checkout automatique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Prix en centimes EUR (source de vérité). Null pour contact_sales.
            $table->unsignedInteger('price_monthly')->nullable();
            $table->unsignedInteger('price_yearly')->nullable();
            $table->string('currency', 3)->default('EUR');

            // Limites commerciales (null = illimité).
            $table->unsignedInteger('max_employees')->nullable();
            $table->unsignedBigInteger('storage_limit_mb')->nullable();
            $table->jsonb('features')->default('[]');

            $table->boolean('contact_sales')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
