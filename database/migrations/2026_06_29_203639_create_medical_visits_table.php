<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('date');
            $table->enum('type', ['embauche', 'periodique', 'reprise', 'spontanee'])->default('periodique');
            $table->enum('resultat', ['apte', 'apte_restrictions', 'inapte'])->default('apte');
            $table->text('restrictions')->nullable();
            $table->date('prochaine_visite')->nullable();
            $table->string('medecin')->nullable();
            $table->string('document')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'date']);
            $table->index('prochaine_visite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_visits');
    }
};
