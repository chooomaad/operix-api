<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorEmployee;
use App\Models\Employee;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    private const LIMIT = 12;

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([
                'employees'            => [],
                'visitors'             => [],
                'contractors'          => [],
                'contractor_employees' => [],
            ]);
        }

        $like = "%{$q}%";

        // Run all 4 queries in parallel using DB::transaction/concurrent isn't possible,
        // but at least keep queries lean and rely on GIN trigram indexes for speed.

        $employees = Employee::whereRaw("
            (COALESCE(nom,'') || ' ' || COALESCE(prenom,'') || ' ' ||
             COALESCE(matricule,'') || ' ' || COALESCE(nni,'') || ' ' ||
             COALESCE(phone,'') || ' ' || COALESCE(email,'') || ' ' ||
             COALESCE(poste,'')) ilike ?", [$like])
            ->with('department:id,name')
            ->limit(self::LIMIT)
            ->get(['id','matricule','nom','prenom','poste','phone','email','photo','department_id','nni','induction_status']);

        $visitors = Visitor::whereRaw("
            (COALESCE(nom,'') || ' ' || COALESCE(prenom,'') || ' ' ||
             COALESCE(phone,'') || ' ' || COALESCE(badge_number,'') || ' ' ||
             COALESCE(nni,'') || ' ' || COALESCE(entreprise,'')) ilike ?", [$like])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get(['id','nom','prenom','phone','entreprise','badge_number','motif','status','checked_in_at','photo']);

        $contractors = Contractor::whereRaw("
            (COALESCE(company_name,'') || ' ' || COALESCE(contact_nom,'') || ' ' ||
             COALESCE(contact_phone,'') || ' ' || COALESCE(num_registre,'') || ' ' ||
             COALESCE(activite,'')) ilike ?", [$like])
            ->limit(self::LIMIT)
            ->get(['id','company_name','contact_nom','contact_phone','activite','status']);

        $contractorEmps = ContractorEmployee::whereRaw("
            (COALESCE(nom,'') || ' ' || COALESCE(prenom,'') || ' ' ||
             COALESCE(phone,'') || ' ' || COALESCE(poste,'') || ' ' ||
             COALESCE(cin,'') || ' ' || COALESCE(badge_number,'')) ilike ?", [$like])
            ->with('contractor:id,company_name')
            ->limit(self::LIMIT)
            ->get(['id','contractor_id','nom','prenom','poste','phone','photo','badge_number','cin','is_active']);

        return response()->json([
            'employees'            => $employees,
            'visitors'             => $visitors,
            'contractors'          => $contractors,
            'contractor_employees' => $contractorEmps,
        ]);
    }
}
