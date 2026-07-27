<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('environment_reports', function (Blueprint $table) {
            $table->jsonb('employees')->nullable()->after('reported_by')
                  ->comment('IDs des employés impliqués');
        });
    }

    public function down(): void
    {
        Schema::table('environment_reports', function (Blueprint $table) {
            $table->dropColumn('employees');
        });
    }
};
