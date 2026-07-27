<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function index(int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $certs = Certification::where('employee_id', $employeeId)
            ->orderByDesc('date_obtention')
            ->get();

        return response()->json($certs);
    }

    public function store(Request $request, int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);

        $validated = $request->validate([
            'type'            => 'required|string|max:100',
            'numero'          => 'nullable|string|max:100',
            'organisme'       => 'nullable|string|max:255',
            'date_obtention'  => 'required|date',
            'date_expiration' => 'nullable|date|after:date_obtention',
            'fichier'         => 'nullable|string|max:255',
            'observations'    => 'nullable|string',
        ]);

        $cert = Certification::create([
            ...$validated,
            'employee_id' => $employeeId,
        ]);

        return response()->json($cert, 201);
    }

    public function update(Request $request, int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $cert = Certification::where('employee_id', $employeeId)->findOrFail($id);

        $validated = $request->validate([
            'type'            => 'sometimes|string|max:100',
            'numero'          => 'nullable|string|max:100',
            'organisme'       => 'nullable|string|max:255',
            'date_obtention'  => 'sometimes|date',
            'date_expiration' => 'nullable|date',
            'fichier'         => 'nullable|string|max:255',
            'observations'    => 'nullable|string',
        ]);

        $cert->update($validated);

        return response()->json($cert);
    }

    public function destroy(int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        Certification::where('employee_id', $employeeId)->findOrFail($id)->delete();

        return response()->json(['message' => 'Certification supprimée.']);
    }
}
