<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute tenant_id (nullable) + index à toutes les tables métier appartenant à une entreprise.
 *
 * Nullable à ce stade : le backfill vers le tenant TCN se fait en commit 5, la FK et le
 * NOT NULL en commit 6. tenant_id est posé DIRECTEMENT sur les tables enfants
 * (formations, certifications, medical_visits, equipment_inspections, contractor_employees)
 * pour une défense en profondeur et un global scope uniforme, sans dépendre du parent.
 *
 * Restent GLOBALES (pas de tenant_id) : users (déjà traité, nullable pour super_admin),
 * otp_tokens (auth transitoire), tables système/Spatie/tenants/demo_requests.
 */
return new class extends Migration
{
    /** @var string[] */
    private array $tables = [
        'departments',
        'employees',
        'safety_incidents',
        'safety_near_miss',
        'environment_reports',
        'gemba_walks',
        'breaches',
        'formations',
        'certifications',
        'medical_visits',
        'visitors',
        'contractors',
        'contractor_employees',
        'permit_to_work',
        'equipment',
        'equipment_inspections',
        'media',
        'notifications',
        'activity_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
