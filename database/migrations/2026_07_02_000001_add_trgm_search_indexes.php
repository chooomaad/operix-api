<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable trigram extension for fast ilike searches
        DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm");

        // Employees — composite text search index
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_employees_trgm
            ON employees
            USING GIN ((
                COALESCE(nom,'') || ' ' ||
                COALESCE(prenom,'') || ' ' ||
                COALESCE(matricule,'') || ' ' ||
                COALESCE(nni,'') || ' ' ||
                COALESCE(phone,'') || ' ' ||
                COALESCE(email,'') || ' ' ||
                COALESCE(poste,'')
            ) gin_trgm_ops)
        ");

        // Visitors
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_visitors_trgm
            ON visitors
            USING GIN ((
                COALESCE(nom,'') || ' ' ||
                COALESCE(prenom,'') || ' ' ||
                COALESCE(phone,'') || ' ' ||
                COALESCE(badge_number,'') || ' ' ||
                COALESCE(nni,'') || ' ' ||
                COALESCE(entreprise,'')
            ) gin_trgm_ops)
        ");

        // Contractors
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_contractors_trgm
            ON contractors
            USING GIN ((
                COALESCE(company_name,'') || ' ' ||
                COALESCE(contact_nom,'') || ' ' ||
                COALESCE(contact_phone,'') || ' ' ||
                COALESCE(num_registre,'') || ' ' ||
                COALESCE(activite,'')
            ) gin_trgm_ops)
        ");

        // Contractor employees
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_contractor_employees_trgm
            ON contractor_employees
            USING GIN ((
                COALESCE(nom,'') || ' ' ||
                COALESCE(prenom,'') || ' ' ||
                COALESCE(phone,'') || ' ' ||
                COALESCE(poste,'') || ' ' ||
                COALESCE(cin,'') || ' ' ||
                COALESCE(badge_number,'')
            ) gin_trgm_ops)
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_employees_trgm");
        DB::statement("DROP INDEX IF EXISTS idx_visitors_trgm");
        DB::statement("DROP INDEX IF EXISTS idx_contractors_trgm");
        DB::statement("DROP INDEX IF EXISTS idx_contractor_employees_trgm");
    }
};
