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
            'titre'           => 'required|string|max:255',
            'numero'          => 'nullable|string|max:100',
            'organisme'       => 'nullable|string|max:255',
            'date_obtention'  => 'required|date',
            'date_expiration' => 'nullable|date|after:date_obtention',
            'image'           => 'nullable|image|max:5120',
        ]);

        $data = collect($validated)->except('image')->all();
        $data['employee_id'] = $employeeId;
        if ($request->hasFile('image')) {
            $data['document'] = app(\App\Services\TenantFileService::class)->store($request->file('image'), 'certifications');
        }

        return response()->json(Certification::create($data), 201);
    }

    public function update(Request $request, int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        $cert = Certification::where('employee_id', $employeeId)->findOrFail($id);

        $validated = $request->validate([
            'titre'           => 'sometimes|string|max:255',
            'numero'          => 'nullable|string|max:100',
            'organisme'       => 'nullable|string|max:255',
            'date_obtention'  => 'sometimes|date',
            'date_expiration' => 'nullable|date',
            'image'           => 'nullable|image|max:5120',
        ]);

        $data = collect($validated)->except('image')->all();
        if ($request->hasFile('image')) {
            $svc = app(\App\Services\TenantFileService::class);
            $data['document'] = $svc->replace($cert->document, $request->file('image'), 'certifications');
        }

        $cert->update($data);

        return response()->json($cert);
    }

    public function destroy(int $employeeId, int $id): JsonResponse
    {
        Employee::findOrFail($employeeId);
        Certification::where('employee_id', $employeeId)->findOrFail($id)->delete();

        return response()->json(['message' => 'Certification supprimée.']);
    }
}
