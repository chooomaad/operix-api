<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de démo (leads) — table GLOBALE plateforme (pas de tenant_id : pré-tenant).
 * Soumises publiquement depuis le site marketing, traitées par le Super Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->text('message')->nullable();

            // new | contacted | approved | rejected | converted
            $table->string('status')->default('new');

            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
