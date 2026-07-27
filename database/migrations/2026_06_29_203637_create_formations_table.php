<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('titre');
            $table->string('organisme')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->integer('duree_jours')->nullable();
            $table->enum('type', ['interne', 'externe', 'elearning', 'habilitation', 'autre'])->default('interne');
            $table->enum('statut', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');
            $table->string('certificat')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'statut']);
            $table->index('date_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
