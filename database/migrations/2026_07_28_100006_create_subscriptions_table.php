<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements — table GLOBALE plateforme (référence un tenant mais N'EST PAS scopée tenant).
 *
 * subscriptions.plan_id = SOURCE DE VÉRITÉ de l'abonnement (Tenant.plan reste un cache
 * dénormalisé). La suspension commerciale (past_due/cancelled/expired) contrôle l'accès
 * mais ne supprime jamais les données du tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // trialing | active | past_due | cancelled | expired
            $table->string('status')->default('trialing');
            $table->string('billing_cycle')->nullable();   // monthly | yearly

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('provider')->nullable();
            $table->string('provider_subscription_id')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
