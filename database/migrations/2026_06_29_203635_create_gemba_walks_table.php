<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemba_walks', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->date('date');
            $table->string('zone');
            $table->string('auditor');
            $table->integer('score')->nullable();
            $table->text('observation');
            $table->text('action_required')->nullable();
            $table->string('responsible')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->string('image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'status']);
            $table->index(['priority', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gemba_walks');
    }
};
