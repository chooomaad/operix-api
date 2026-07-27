<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractorEmployeeController extends Controller
{
    public function index(Request $request, int $contractorId): JsonResponse
    {
        $contractor = Contractor::findOrFail($contractorId);

        $query = $contractor->employees()->when(
            $request->filled('search'),
            fn($q) => $q->where(fn($w) => $w
                ->where('nom',    'ilike', "%{$request->search}%")
                ->orWhere('prenom', 'ilike', "%{$request->search}%")
                ->orWhere('poste',  'ilike', "%{$request->search}%")
                ->orWhere('cin',    'ilike', "%{$request->search}%")
            )
        );

        $employees = $query->orderBy('nom')->get([
            'id', 'contractor_id', 'nom', 'prenom', 'poste', 'phone',
            'cin', 'badge_number', 'date_debut', 'date_fin',
            'habilitation_hsse', 'habilitation_date', 'is_active',
        ]);

        return response()->json([
            'contractor' => ['id' => $contractor->id, 'company_name' => $contractor->company_name],
            'employees'  => $employees,
            'total'      => $employees->count(),
        ]);
    }

    public function store(Request $request, int $contractorId): JsonResponse
    {
        Contractor::findOrFail($contractorId);

        $data = $request->validate([
            'nom'               => ['required', 'string', 'max:255'],
            'prenom'            => ['required', 'string', 'max:255'],
            'poste'             => ['nullable', 'string', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'cin'               => ['nullable', 'string', 'max:50'],
            'badge_number'      => ['nullable', 'string', 'max:50'],
            'date_debut'        => ['nullable', 'date'],
            'date_fin'          => ['nullable', 'date', 'after_or_equal:date_debut'],
            'habilitation_hsse' => ['nullable', 'boolean'],
            'habilitation_date' => ['nullable', 'date'],
            'is_active'         => ['boolean'],
        ]);

        $data['contractor_id'] = $contractorId;
        $data['is_active']     = $data['is_active'] ?? true;

        $employee = ContractorEmployee::create($data);

        return response()->json($employee, 201);
    }

    public function update(Request $request, int $contractorId, int $id): JsonResponse
    {
        $employee = ContractorEmployee::where('contractor_id', $contractorId)->findOrFail($id);

        $data = $request->validate([
            'nom'               => ['sometimes', 'string', 'max:255'],
            'prenom'            => ['sometimes', 'string', 'max:255'],
            'poste'             => ['nullable', 'string', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'cin'               => ['nullable', 'string', 'max:50'],
            'badge_number'      => ['nullable', 'string', 'max:50'],
            'date_debut'        => ['nullable', 'date'],
            'date_fin'          => ['nullable', 'date'],
            'habilitation_hsse' => ['nullable', 'boolean'],
            'habilitation_date' => ['nullable', 'date'],
            'is_active'         => ['boolean'],
        ]);

        $employee->update($data);

        return response()->json($employee);
    }

    public function destroy(int $contractorId, int $id): JsonResponse
    {
        ContractorEmployee::where('contractor_id', $contractorId)->findOrFail($id)->delete();
        return response()->json(['message' => 'Personnel supprimé.']);
    }
}
