<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence : `last_seen_at` marque la derniere activite reelle d'un compte
 * (mise a jour a chaque requete API authentifiee, au plus une fois par minute).
 * Distinct de `last_login_at`, qui ne bouge qu'a la connexion. « En ligne » se
 * derive de last_seen_at recent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
