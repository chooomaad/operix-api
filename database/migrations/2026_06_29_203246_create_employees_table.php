<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('matricule')->unique();
            $table->string('nni', 20)->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('poste');
            $table->string('section', 150)->nullable();
            $table->string('entreprise', 150)->nullable();
            $table->enum('type_contrat', ['CDI', 'CDD', 'Stage', 'Prestataire', 'Autre'])->default('CDI');
            $table->date('date_embauche')->nullable();
            $table->date('date_fin_contrat')->nullable();
            $table->string('photo')->nullable();
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('nationalite')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('adresse')->nullable();
            $table->string('num_cni')->nullable();
            $table->string('contact_urgence_nom')->nullable();
            $table->string('contact_urgence_tel', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('induction_status')->nullable();
            $table->date('induction_start_date')->nullable();
            $table->string('last_modified_by', 100)->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenom']);
            $table->index('is_active');
            $table->index('type_contrat');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
