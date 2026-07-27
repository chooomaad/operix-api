<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->enum('category', ['vehicle', 'crane', 'forklift', 'electrical', 'pressure', 'fire', 'ppe', 'tool', 'other'])->default('other');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('last_inspection')->nullable();
            $table->date('next_inspection')->nullable();
            $table->integer('inspection_frequency_days')->default(365);
            $table->enum('status', ['operational', 'maintenance', 'out_of_service', 'retired'])->default('operational');
            $table->string('location')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category']);
            $table->index('next_inspection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
