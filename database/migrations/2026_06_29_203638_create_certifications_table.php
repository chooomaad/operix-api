<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('titre');
            $table->string('organisme')->nullable();
            $table->date('date_obtention');
            $table->date('date_expiration')->nullable();
            $table->string('numero')->nullable();
            $table->string('document')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'is_expired']);
            $table->index('date_expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
