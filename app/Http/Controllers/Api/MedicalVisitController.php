<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MedicalVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalVisitController extends Controller
{
    public function index(int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $visits = MedicalVisit::where('employee_id', $employeeId)
            ->orderByDesc('date')
            ->get();

        return response()->json($visits);
    }

    public function store(Request $request, int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);

        $validated = $request->validate([
            'date'             => 'required|date',
            'type'             => 'in:embauche,periodique,reprise,spontanee',
            'resultat'         => 'in:apte,apte_restrictions,inapte',
            'restrictions'     => 'nullable|string',
            'prochaine_visite' => 'nullable|date|after:date',
            'medecin'          => 'nullable|string|max:255',
            'document'         => 'nullable|string|max:255',
            'observations'     => 'nullable|string',
        ]);

        $visit = MedicalVisit::create([
            ...$validated,
            'employee_id' => $employeeId,
        ]);

        return response()->json($visit, 201);
    }

    public function update(Request $request, int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $visit = MedicalVisit::where('employee_id', $employeeId)->findOrFail($id);

        $validated = $request->validate([
            'date'             => 'sometimes|date',
            'type'             => 'in:embauche,periodique,reprise,spontanee',
            'resultat'         => 'in:apte,apte_restrictions,inapte',
            'restrictions'     => 'nullable|string',
            'prochaine_visite' => 'nullable|date',
            'medecin'          => 'nullable|string|max:255',
            'document'         => 'nullable|string|max:255',
            'observations'     => 'nullable|string',
        ]);

        $visit->update($validated);

        return response()->json($visit);
    }

    public function destroy(int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        MedicalVisit::where('employee_id', $employeeId)->findOrFail($id)->delete();

        return response()->json(['message' => 'Visite médicale supprimée.']);
    }
}
