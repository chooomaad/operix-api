<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Formation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index(int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $formations = Formation::where('employee_id', $employeeId)
            ->orderByDesc('date_debut')
            ->get();

        return response()->json($formations);
    }

    public function store(Request $request, int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);

        $validated = $request->validate([
            'titre'        => 'required|string|max:255',
            'organisme'    => 'nullable|string|max:255',
            'date_debut'   => 'required|date',
            'date_fin'     => 'nullable|date|after_or_equal:date_debut',
            'duree_jours'  => 'nullable|integer|min:1',
            'type'         => 'in:interne,externe,elearning,habilitation,autre',
            'statut'       => 'in:planifiee,en_cours,terminee,annulee',
            'certificat'   => 'nullable|string|max:255',
            'observations' => 'nullable|string',
        ]);

        $formation = Formation::create([
            ...$validated,
            'employee_id' => $employeeId,
        ]);

        return response()->json($formation, 201);
    }

    public function update(Request $request, int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $formation = Formation::where('employee_id', $employeeId)->findOrFail($id);

        $validated = $request->validate([
            'titre'        => 'sometimes|string|max:255',
            'organisme'    => 'nullable|string|max:255',
            'date_debut'   => 'sometimes|date',
            'date_fin'     => 'nullable|date',
            'duree_jours'  => 'nullable|integer|min:1',
            'type'         => 'in:interne,externe,elearning,habilitation,autre',
            'statut'       => 'in:planifiee,en_cours,terminee,annulee',
            'certificat'   => 'nullable|string|max:255',
            'observations' => 'nullable|string',
        ]);

        $formation->update($validated);

        return response()->json($formation);
    }

    public function destroy(int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        Formation::where('employee_id', $employeeId)->findOrFail($id)->delete();

        return response()->json(['message' => 'Formation supprimée.']);
    }
}
