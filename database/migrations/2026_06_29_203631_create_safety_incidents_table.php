<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('location');
            $table->enum('type', ['LTI', 'MTC', 'RWC', 'FAC', 'HPI', 'Fire', 'Security', 'Autre'])->default('Autre');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->text('immediate_cause')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('corrective_action_due')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('employees')->default('[]');
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'status']);
            $table->index(['type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_incidents');
    }
};
