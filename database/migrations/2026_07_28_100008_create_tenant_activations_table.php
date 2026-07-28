<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons d'activation de compte (premier company_admin d'un tenant).
 *
 * Table dédiée (on ne détourne PAS password_reset_tokens). Le token n'est stocké que
 * sous forme HACHÉE (jamais en clair) ; usage unique (used_at) ; expiration courte.
 * Aucun mot de passe n'est jamais envoyé par email : l'utilisateur définit son accès
 * via le lien d'activation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_activations');
    }
};
