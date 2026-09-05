<?php

namespace App\Http\Controllers\Api;

use App\Events\HseEventCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyDamage\StorePropertyDamageRequest;
use App\Http\Requests\PropertyDamage\UpdatePropertyDamageRequest;
use App\Http\Resources\PropertyDamageResource;
use App\Models\PropertyDamage;
use App\Services\TenantFileService;
use App\Support\HseEventPayload;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyDamageController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = PropertyDamage::query()->with('reporter');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('description', 'ilike', "%{$s}%")
                ->orWhere('location',  'ilike', "%{$s}%")
                ->orWhere('reference', 'ilike', "%{$s}%")
            );
        }

        if ($request->filled('type'))     $query->where('type',     $request->type);
        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('status'))   $query->where('status',   $request->status);
        if ($request->filled('from'))     $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))       $query->whereDate('date', '<=', $request->to);

        $result = $this->paginateQuery($query->orderByDesc('date'), $request);
        $result['data'] = PropertyDamageResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StorePropertyDamageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reported_by'] = $request->user()->id;

        $files = app(TenantFileService::class);
        if ($request->hasFile('image')) {
            $data['image'] = $files->store($request->file('image'), 'property-damage');
        }
        if ($request->hasFile('report_file')) {
            $data['report_file'] = $files->store($request->file('report_file'), 'property-damage/reports');
        }

        $damage = $this->createWithReference('PDM', PropertyDamage::class, $data);
        $damage->load('reporter');

        // Diffusion temps reel — comme les autres évènements HSSE (ShouldBroadcast).
        HseEventCreated::dispatch(HseEventPayload::fromModel($damage));

        return response()->json(new PropertyDamageResource($damage), 201);
    }

    public function show(int $id): JsonResponse
    {
        $damage = PropertyDamage::with('reporter')->findOrFail($id);
        return response()->json(new PropertyDamageResource($damage));
    }

    public function update(UpdatePropertyDamageRequest $request, int $id): JsonResponse
    {
        $damage = PropertyDamage::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('report_file')) {
            $data['report_file'] = app(TenantFileService::class)
                ->replace($damage->report_file, $request->file('report_file'), 'property-damage/reports');
        }

        $damage->update($data);
        $damage->load('reporter');

        return response()->json(new PropertyDamageResource($damage));
    }

    public function destroy(int $id): JsonResponse
    {
        PropertyDamage::findOrFail($id)->delete();
        return response()->json(['message' => 'Dommage matériel supprimé.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'corrective_action' => ['required', 'string'],
        ]);

        $damage = PropertyDamage::findOrFail($id);
        $damage->update([
            'status'            => 'closed',
            'corrective_action' => $request->corrective_action,
        ]);

        return response()->json(new PropertyDamageResource($damage));
    }

    public function stats(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) date('Y'));
        $base = PropertyDamage::query()->whereYear('date', $year);

        return response()->json([
            'total'          => (clone $base)->count(),
            'open'           => (clone $base)->where('status', 'open')->count(),
            'closed'         => (clone $base)->where('status', 'closed')->count(),
            'estimated_cost' => (float) (clone $base)->sum('estimated_cost'),
            'by_type'        => (clone $base)->selectRaw('type, COUNT(*) as total')
                                    ->groupBy('type')->pluck('total', 'type'),
            'by_severity'    => (clone $base)->selectRaw('severity, COUNT(*) as total')
                                    ->groupBy('severity')->pluck('total', 'severity'),
        ]);
    }
}
