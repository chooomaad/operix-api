<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Mode « leger » pour la recherche du selecteur de personnes : on evite les
        // sous-requetes de comptage et le chargement du departement, inutiles pour
        // une recherche as-you-type — la reponse est plus rapide et plus legere.
        $light = $request->boolean('light');

        $query = Employee::query();
        if (! $light) {
            $query->with('department')->withCount(['formations', 'certifications']);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nom',       'ilike', "%{$s}%")
                  ->orWhere('prenom',  'ilike', "%{$s}%")
                  ->orWhere('matricule','ilike', "%{$s}%")
                  ->orWhere('poste',   'ilike', "%{$s}%");
            });
        }

        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('type_contrat'))  $query->where('type_contrat',  $request->type_contrat);
        if ($request->filled('poste'))         $query->where('poste', 'ilike', '%' . $request->poste . '%');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage   = min($request->integer('per_page', 25), 100);
        $employees = $query->orderBy('nom')->orderBy('prenom')->paginate($perPage);

        return response()->json([
            'data' => EmployeeResource::collection($employees->items()),
            'meta' => [
                'total'        => $employees->total(),
                'per_page'     => $employees->perPage(),
                'current_page' => $employees->currentPage(),
                'last_page'    => $employees->lastPage(),
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = app(\App\Services\TenantFileService::class)->store($request->file('photo'), 'employees');
        }

        $employee = Employee::create($data);
        $employee->load('department');

        return response()->json(new EmployeeResource($employee), 201);
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::with([
            'department', 'formations', 'certifications', 'medicalVisits', 'breaches',
        ])->withCount(['formations', 'certifications'])->findOrFail($id);

        return response()->json(new EmployeeResource($employee));
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $data     = $request->validated();

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                app(\App\Services\TenantFileService::class)->delete($employee->photo);
            }
            $data['photo'] = app(\App\Services\TenantFileService::class)->store($request->file('photo'), 'employees');
        }

        $employee->update($data);
        $employee->load('department');

        return response()->json(new EmployeeResource($employee));
    }

    public function destroy(int $id): JsonResponse
    {
        Employee::findOrFail($id)->delete();
        return response()->json(['message' => 'Employé supprimé.']);
    }

    public function history(int $id): JsonResponse
    {
        $employee = Employee::with([
            'formations'    => fn ($q) => $q->orderByDesc('date_debut'),
            'certifications'=> fn ($q) => $q->orderByDesc('date_obtention'),
            'medicalVisits' => fn ($q) => $q->orderByDesc('date'),
            'breaches'      => fn ($q) => $q->orderByDesc('date'),
        ])->findOrFail($id);

        // Incidents où cet employé est impliqué (JSON array)
        $incidents = \App\Models\SafetyIncident::whereRaw(
            "employees @> ?::jsonb", [json_encode([$id])]
        )->orderByDesc('date')->get()->map(fn ($i) => [
            'id'          => $i->id,
            'reference'   => $i->reference,
            'date'        => $i->date?->format('d/m/Y'),
            'location'    => $i->location,
            'type'        => $i->type,
            'severity'    => $i->severity,
            'status'      => $i->status,
            'description' => Str::limit($i->description, 80),
        ]);

        // Near-miss où cet employé est impliqué
        $nearMiss = \App\Models\SafetyNearMiss::whereRaw(
            "employees @> ?::jsonb", [json_encode([$id])]
        )->orderByDesc('date')->get()->map(fn ($n) => [
            'id'          => $n->id,
            'reference'   => $n->reference,
            'date'        => $n->date?->format('d/m/Y'),
            'location'    => $n->location,
            'severity'    => $n->severity,
            'status'      => $n->status,
            'description' => Str::limit($n->description, 80),
        ]);

        // Infractions liées à cet employé
        $breaches = \App\Models\Breach::where(function ($q) use ($id) {
                $q->where('employee_id', $id)
                  ->orWhereRaw('employees @> ?::jsonb', [json_encode([$id])]);
            })->orderByDesc('date')->get()->map(fn ($b) => [
            'id'          => $b->id,
            'reference'   => $b->reference,
            'date'        => $b->date?->format('d/m/Y'),
            'type'        => $b->type,
            'severity'    => $b->severity,
            'status'      => $b->status,
            'description' => Str::limit($b->description, 80),
        ]);

        // Rapports environnementaux où cet employé est impliqué
        $environment = \App\Models\EnvironmentReport::whereRaw(
            "employees @> ?::jsonb", [json_encode([$id])]
        )->orderByDesc('date')->get()->map(fn ($e) => [
            'id'          => $e->id,
            'reference'   => $e->reference,
            'date'        => $e->date?->format('d/m/Y'),
            'location'    => $e->location,
            'type'        => $e->type,
            'severity'    => $e->severity,
            'status'      => $e->status,
            'description' => Str::limit($e->description, 80),
        ]);

        return response()->json([
            'employee'       => new EmployeeResource($employee),
            'formations'     => $employee->formations,
            'certifications' => $employee->certifications,
            'medical_visits' => $employee->medicalVisits,
            'incidents'      => $incidents,
            'near_miss'      => $nearMiss,
            'breaches'       => $breaches,
            'environment'    => $environment,
            'stats' => [
                'incidents_count'     => $incidents->count(),
                'near_miss_count'     => $nearMiss->count(),
                'breaches_count'      => $breaches->count(),
                'environment_count'   => $environment->count(),
                'formations_count'    => $employee->formations->count(),
                'certifications_count'=> $employee->certifications->count(),
            ],
        ]);
    }
}
