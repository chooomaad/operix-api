<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenom');
            $table->string('phone', 20)->nullable();
            $table->string('poste')->nullable();
            $table->string('cin')->nullable();
            $table->string('badge_number')->nullable();
            $table->string('photo')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('habilitation_hsse')->default(false);
            $table->date('habilitation_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_employees');
    }
};
