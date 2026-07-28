<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Environment\StoreEnvironmentRequest;
use App\Http\Requests\Environment\UpdateEnvironmentRequest;
use App\Http\Resources\EnvironmentResource;
use App\Models\EnvironmentReport;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = EnvironmentReport::query()->with('reporter');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('description', 'ilike', "%{$s}%")
                ->orWhere('location',  'ilike', "%{$s}%")
            );
        }

        if ($request->filled('type'))     $query->where('type',     $request->type);
        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('status'))   $query->where('status',   $request->status);
        if ($request->filled('from'))     $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))       $query->whereDate('date', '<=', $request->to);

        $result = $this->paginateQuery($query->orderByDesc('date'), $request);
        $result['data'] = EnvironmentResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreEnvironmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reported_by'] = $request->user()->id;
        $data['reference']   = $this->generateReference('ENV', EnvironmentReport::class);

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\TenantFileService::class)->store($request->file('image'), 'environment');
        }

        $report = EnvironmentReport::create($data);
        $report->load('reporter');

        return response()->json(new EnvironmentResource($report), 201);
    }

    public function show(int $id): JsonResponse
    {
        $report = EnvironmentReport::with('reporter')->findOrFail($id);
        return response()->json(new EnvironmentResource($report));
    }

    public function update(UpdateEnvironmentRequest $request, int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);
        $report->update($request->validated());
        $report->load('reporter');

        return response()->json(new EnvironmentResource($report));
    }

    public function destroy(int $id): JsonResponse
    {
        EnvironmentReport::findOrFail($id)->delete();
        return response()->json(['message' => 'Rapport environnemental supprimé.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'corrective_action' => ['required', 'string'],
        ]);

        $report = EnvironmentReport::findOrFail($id);
        $report->update([
            'status'            => 'closed',
            'corrective_action' => $request->corrective_action,
        ]);

        return response()->json(new EnvironmentResource($report));
    }

    public function stats(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) date('Y'));
        $base = EnvironmentReport::query()->whereYear('date', $year);

        return response()->json([
            'total'       => (clone $base)->count(),
            'open'        => (clone $base)->where('status', 'open')->count(),
            'closed'      => (clone $base)->where('status', 'closed')->count(),
            'by_type'     => (clone $base)->selectRaw('type, COUNT(*) as total')
                                ->groupBy('type')->pluck('total', 'type'),
            'by_severity' => (clone $base)->selectRaw('severity, COUNT(*) as total')
                                ->groupBy('severity')->pluck('total', 'severity'),
        ]);
    }
}
