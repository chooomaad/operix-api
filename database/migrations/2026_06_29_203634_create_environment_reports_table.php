<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->date('date');
            $table->string('location');
            $table->enum('type', ['spill', 'emission', 'waste', 'noise', 'other'])->default('other');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->text('impact')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('corrective_action_due')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_reports');
    }
};
