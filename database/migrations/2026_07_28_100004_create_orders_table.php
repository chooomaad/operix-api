<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commandes commerciales — table GLOBALE plateforme.
 *
 * Une commande existe INDÉPENDAMMENT d'un paiement (status pending au départ).
 * amount/currency sont calculés CÔTÉ SERVEUR depuis le plan (jamais depuis le client).
 * amount en centimes EUR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->foreignId('plan_id')->constrained('plans');

            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 40)->nullable();

            $table->string('billing_cycle');           // monthly | yearly
            $table->unsignedInteger('amount');          // centimes (devise ci-dessous)
            $table->string('currency', 3)->default('EUR');

            // pending | paid | failed | cancelled | refunded
            $table->string('status')->default('pending');

            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('demo_request_id')->nullable()->constrained('demo_requests')->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
