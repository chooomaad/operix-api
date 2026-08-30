<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons de reinitialisation du PIN.
 *
 * Table dediee plutot que reutiliser `otp_tokens` (codes a 6 chiffres du login)
 * ou `tenant_activations` (activation initiale du compte) : les trois cycles de
 * vie sont distincts, les melanger creerait des interferences (un jeton de reset
 * resolu comme une activation, par exemple).
 *
 * Le token n'est JAMAIS stocke en clair : seul son hash SHA-256 l'est. La
 * recherche reste deterministe (hash du token recu), mais une fuite de la base
 * ne livre aucun lien exploitable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pin_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_reset_tokens');
    }
};
