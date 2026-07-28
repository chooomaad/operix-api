<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Unicité du nom PAR TENANT (tenant résolu serveur).
            'name'        => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where('tenant_id', $request->user()->tenant_id)],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $department = Department::create($validated);

        return response()->json($department, 201);
    }

    public function show(int $id): JsonResponse
    {
        $department = Department::withCount('employees')->findOrFail($id);
        return response()->json($department);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255', Rule::unique('departments', 'name')->where('tenant_id', $request->user()->tenant_id)->ignore($id)],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $department->update($validated);

        return response()->json($department);
    }

    public function destroy(int $id): JsonResponse
    {
        $department = Department::withCount('employees')->findOrFail($id);

        abort_if($department->employees_count > 0, 422,
            'Ce département contient des employés. Réaffectez-les avant de le supprimer.');

        $department->delete();

        return response()->json(['message' => 'Département supprimé.']);
    }
}
