<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_to_work', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->enum('type', ['hot_work', 'confined_space', 'electrical', 'working_at_height', 'excavation', 'chemical', 'general'])->default('general');
            $table->string('title');
            $table->text('description');
            $table->string('location');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->string('contractor_name')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_to');
            $table->enum('status', ['draft', 'pending', 'approved', 'active', 'closed', 'cancelled'])->default('draft');
            $table->jsonb('risks')->default('[]');
            $table->jsonb('precautions')->default('[]');
            $table->jsonb('ppe_required')->default('[]');
            $table->jsonb('workers')->default('[]');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_to_work');
    }
};
