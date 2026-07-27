<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('entreprise')->nullable();
            $table->string('nni', 20)->nullable();
            $table->string('cin')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('badge_number')->nullable();
            $table->string('motif');
            $table->string('personne_visitee')->nullable();
            $table->string('department')->nullable();
            $table->enum('status', ['in', 'out'])->default('in');
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
