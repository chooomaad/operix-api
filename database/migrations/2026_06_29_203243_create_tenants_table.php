<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table organisation (configuration unique du TCN)
 * et ajoute les colonnes métier à la table users.
 *
 * ADR-001 : Architecture mono-instance, pas de multi-tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Terminal à Conteneurs de Nouakchott');
            $table->string('short_name', 20)->default('TCN');
            $table->string('logo')->nullable();
            $table->string('primary_color', 7)->default('#0f2847');
            $table->string('country', 2)->default('MR');
            $table->string('timezone')->default('Africa/Nouakchott');
            $table->string('locale', 5)->default('fr');
            $table->jsonb('settings')->default('{}');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'agent'])->default('agent');
            $table->string('matricule')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'matricule', 'phone', 'avatar', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('organisation');
    }
};
