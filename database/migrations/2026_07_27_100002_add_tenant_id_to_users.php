<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute users.tenant_id (nullable à ce stade).
 *
 * Nullable volontairement : le super_admin (rôle plateforme) reste tenant_id = NULL.
 * Le backfill des utilisateurs existants vers le tenant TCN est fait en commit 5 ;
 * la FK est ajoutée en commit 6 (après création du tenant TCN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
