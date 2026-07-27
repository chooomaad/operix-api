<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Http\Requests\Incident\UpdateIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\SafetyIncident;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = SafetyIncident::query()->with('reporter');

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
        $result['data'] = IncidentResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reported_by'] = $request->user()->id;
        $data['reference']   = $this->generateReference('INC', SafetyIncident::class);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('operix/incidents', 'public');
        }

        $incident = SafetyIncident::create($data);
        $incident->load('reporter');

        return response()->json(new IncidentResource($incident), 201);
    }

    public function show(int $id): JsonResponse
    {
        $incident = SafetyIncident::with('reporter')->findOrFail($id);
        return response()->json(new IncidentResource($incident));
    }

    public function update(UpdateIncidentRequest $request, int $id): JsonResponse
    {
        $incident = SafetyIncident::findOrFail($id);
        $incident->update($request->validated());
        $incident->load('reporter');

        return response()->json(new IncidentResource($incident));
    }

    public function destroy(int $id): JsonResponse
    {
        SafetyIncident::findOrFail($id)->delete();
        return response()->json(['message' => 'Incident supprimé.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'root_cause'        => ['required', 'string'],
            'corrective_action' => ['required', 'string'],
        ]);

        $incident = SafetyIncident::findOrFail($id);
        $incident->update([
            'status'            => 'closed',
            'root_cause'        => $request->root_cause,
            'corrective_action' => $request->corrective_action,
        ]);

        return response()->json(new IncidentResource($incident));
    }

    public function stats(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) date('Y'));
        $base = SafetyIncident::query()->whereYear('date', $year);

        return response()->json([
            'total'       => (clone $base)->count(),
            'open'        => (clone $base)->where('status', 'open')->count(),
            'closed'      => (clone $base)->where('status', 'closed')->count(),
            'by_type'     => (clone $base)->selectRaw('type, COUNT(*) as total')
                                ->groupBy('type')->pluck('total', 'type'),
            'by_severity' => (clone $base)->selectRaw('severity, COUNT(*) as total')
                                ->groupBy('severity')->pluck('total', 'severity'),
            'by_month'    => (clone $base)->selectRaw("TO_CHAR(date,'MM') as month, COUNT(*) as total")
                                ->groupBy('month')->orderBy('month')->pluck('total', 'month'),
        ]);
    }
}
