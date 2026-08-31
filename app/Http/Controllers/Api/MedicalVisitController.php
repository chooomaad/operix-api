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
            'type'             => 'nullable|in:embauche,periodique,reprise,spontanee',
            'resultat'         => 'nullable|in:apte,apte_restrictions,inapte',
            'restrictions'     => 'nullable|string',
            'prochaine_visite' => 'nullable|date|after:date',
            'medecin'          => 'nullable|string|max:255',
            'observations'     => 'nullable|string',
            'image'            => 'nullable|image|max:5120',
        ]);

        $data = collect($validated)->except('image')->all();
        $data['employee_id'] = $employeeId;
        // `type` et `resultat` sont NOT NULL en base : défaut sûr.
        $data['type']     = $validated['type']     ?? 'periodique';
        $data['resultat'] = $validated['resultat'] ?? 'apte';
        if ($request->hasFile('image')) {
            $data['document'] = app(\App\Services\TenantFileService::class)->store($request->file('image'), 'medical-visits');
        }

        return response()->json(MedicalVisit::create($data), 201);
    }

    public function update(Request $request, int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $visit = MedicalVisit::where('employee_id', $employeeId)->findOrFail($id);

        $validated = $request->validate([
            'date'             => 'sometimes|date',
            'type'             => 'nullable|in:embauche,periodique,reprise,spontanee',
            'resultat'         => 'nullable|in:apte,apte_restrictions,inapte',
            'restrictions'     => 'nullable|string',
            'prochaine_visite' => 'nullable|date',
            'medecin'          => 'nullable|string|max:255',
            'observations'     => 'nullable|string',
            'image'            => 'nullable|image|max:5120',
        ]);

        $data = collect($validated)->except('image')->all();
        if ($request->hasFile('image')) {
            $svc = app(\App\Services\TenantFileService::class);
            $data['document'] = $svc->replace($visit->document, $request->file('image'), 'medical-visits');
        }

        $visit->update($data);

        return response()->json($visit);
    }

    public function destroy(int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        MedicalVisit::where('employee_id', $employeeId)->findOrFail($id)->delete();

        return response()->json(['message' => 'Visite médicale supprimée.']);
    }
}
