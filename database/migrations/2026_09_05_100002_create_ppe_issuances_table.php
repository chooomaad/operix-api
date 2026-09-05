<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dotation EPI (Équipements de Protection Individuelle) remis aux personnes.
 *
 * Rattachée à N'IMPORTE QUELLE personne via (person_type, person_id) — même mécanisme
 * générique que les autres dossiers RH (formations / certifications / visites médicales,
 * cf. PersonRecordController). Concerne en premier lieu les employés, mais le format
 * générique couvre aussi sous-traitants / stagiaires / visiteurs sans surcoût.
 *
 * Un enregistrement = une remise d'EPI (casque, chaussures, gants, gilet…), avec taille,
 * quantité, date de remise, retour prévu, état et justificatif éventuel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppe_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Personne bénéficiaire (employee | contractor | visitor | intern).
            $table->string('person_type');
            $table->unsignedBigInteger('person_id');

            $table->string('designation');                 // « Casque de sécurité », « Chaussures S3 »…
            $table->string('category')->nullable();         // head | eyes | hearing | respiratory | hands | feet | body | fall | other
            $table->string('size')->nullable();             // taille (42, L, XL…)
            $table->unsignedInteger('quantity')->default(1);
            $table->date('issued_at');                      // date de remise
            $table->date('return_due')->nullable();         // retour / renouvellement prévu
            $table->string('condition')->default('neuf');   // neuf | bon | use | a_remplacer
            $table->string('document')->nullable();         // justificatif (bon de remise signé, PDF/image)
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'person_type', 'person_id']);
            $table->index('return_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppe_issuances');
    }
};
