<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements — table GLOBALE plateforme.
 *
 * UNIQUE(provider, provider_transaction_id) → protection anti-replay / idempotence au
 * niveau base : un même événement provider ne peut être enregistré deux fois.
 * sanitized_payload = payload ASSAINI (allowlist) — jamais de carte/CVV/secret/signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');

            $table->string('provider');
            $table->string('provider_transaction_id');

            $table->unsignedInteger('amount');       // centimes
            $table->string('currency', 3);

            // pending | succeeded | failed | refunded
            $table->string('status')->default('pending');

            $table->timestamp('paid_at')->nullable();
            $table->jsonb('sanitized_payload')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
