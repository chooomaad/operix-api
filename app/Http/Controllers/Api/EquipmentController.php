<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Traits\HandlesApiResources;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = Equipment::query()->with('assignedEmployee');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('name',          'ilike', "%{$s}%")
                ->orWhere('code',        'ilike', "%{$s}%")
                ->orWhere('serial_number','ilike', "%{$s}%")
            );
        }

        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('status'))   $query->where('status',   $request->status);

        if ($request->boolean('inspection_due')) {
            $query->whereNotNull('next_inspection')
                  ->whereDate('next_inspection', '<=', now());
        }

        $result = $this->paginateQuery($query->orderBy('name'), $request);
        $result['data'] = EquipmentResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = app(\App\Services\TenantFileService::class)->store($request->file('photo'), 'equipment');
        }

        $equipment = Equipment::create($data);
        $equipment->load('assignedEmployee');

        return response()->json(new EquipmentResource($equipment), 201);
    }

    public function show(int $id): JsonResponse
    {
        $equipment = Equipment::with(['assignedEmployee', 'inspections'])->findOrFail($id);
        return response()->json(new EquipmentResource($equipment));
    }

    public function update(UpdateEquipmentRequest $request, int $id): JsonResponse
    {
        $equipment = Equipment::findOrFail($id);
        $equipment->update($request->validated());
        $equipment->load('assignedEmployee');

        return response()->json(new EquipmentResource($equipment));
    }

    public function destroy(int $id): JsonResponse
    {
        Equipment::findOrFail($id)->delete();
        return response()->json(['message' => 'Équipement supprimé.']);
    }

    public function inspect(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'date'             => ['required', 'date'],
            'type'             => ['nullable', 'in:periodic,pre_use,post_incident,regulatory'],
            'result'           => ['required', 'in:pass,fail,conditional'],
            'observations'     => ['nullable', 'string'],
            'actions_required' => ['nullable', 'string'],
            'inspector'        => ['required', 'string', 'max:255'],
        ]);

        $equipment      = Equipment::findOrFail($id);
        $nextInspection = Carbon::parse($request->date)
            ->addDays($equipment->inspection_frequency_days);

        $equipment->inspections()->create([
            'date'             => $request->date,
            'type'             => $request->get('type', 'periodic'),
            'result'           => $request->result,
            'observations'     => $request->observations,
            'actions_required' => $request->actions_required,
            'next_inspection'  => $nextInspection,
            'inspector'        => $request->inspector,
            'created_by'       => $request->user()->id,
        ]);

        $equipment->update([
            'last_inspection' => $request->date,
            'next_inspection' => $nextInspection,
        ]);

        $equipment->load('assignedEmployee');

        return response()->json(new EquipmentResource($equipment));
    }
}
