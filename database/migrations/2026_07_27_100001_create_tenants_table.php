<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la VRAIE table `tenants` (entreprise cliente Operix).
 *
 * NB : la migration 2026_06_29_203243_create_tenants_table (mal nommée) crée en réalité
 * la table `organisation` (config mono-instance TCN, ADR-001). Elle est CONSERVÉE comme
 * vestige ; son branding sera migré vers `tenants` puis dépréciée (commit 11).
 *
 * Champs branding (name/short_name/logo/primary_color/country/timezone/locale/settings)
 * absorbés depuis `organisation` : chaque tenant porte désormais sa propre identité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->string('slug')->unique();

            // Cycle de vie plateforme
            $table->enum('status', ['active', 'trial', 'suspended'])->default('trial');
            $table->enum('plan', ['starter', 'pro', 'enterprise'])->default('starter');
            $table->unsignedInteger('max_employees')->default(100);
            $table->timestamp('demo_expires_at')->nullable();

            // Branding (absorbé depuis organisation)
            $table->string('logo')->nullable();
            $table->string('primary_color', 7)->default('#0f2847');
            $table->string('country', 2)->default('MR');
            $table->string('timezone')->default('Africa/Nouakchott');
            $table->string('locale', 5)->default('fr');
            $table->jsonb('settings')->default('{}');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
