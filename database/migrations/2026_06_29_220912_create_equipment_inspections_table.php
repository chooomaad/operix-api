<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['periodic', 'pre_use', 'post_incident', 'regulatory'])->default('periodic');
            $table->enum('result', ['pass', 'fail', 'conditional'])->default('pass');
            $table->text('observations')->nullable();
            $table->text('actions_required')->nullable();
            $table->date('next_inspection')->nullable();
            $table->string('inspector');
            $table->string('document')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_inspections');
    }
};
