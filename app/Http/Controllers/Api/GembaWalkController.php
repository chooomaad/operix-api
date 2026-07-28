<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GembaWalk\StoreGembaWalkRequest;
use App\Http\Requests\GembaWalk\UpdateGembaWalkRequest;
use App\Http\Resources\GembaWalkResource;
use App\Models\GembaWalk;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GembaWalkController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = GembaWalk::query()->with('creator');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('observation',   'ilike', "%{$s}%")
                ->orWhere('zone',        'ilike', "%{$s}%")
                ->orWhere('auditor',     'ilike', "%{$s}%")
                ->orWhere('responsible', 'ilike', "%{$s}%")
            );
        }

        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('status'))   $query->where('status',   $request->status);
        if ($request->filled('zone'))     $query->where('zone', 'ilike', "%{$request->zone}%");
        if ($request->filled('from'))     $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))       $query->whereDate('date', '<=', $request->to);

        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'resolved')
                  ->whereNotNull('due_date')
                  ->whereDate('due_date', '<', now());
        }

        $result = $this->paginateQuery($query->orderByDesc('date'), $request);
        $result['data'] = GembaWalkResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreGembaWalkRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\TenantFileService::class)->store($request->file('image'), 'gemba');
        }

        $walk = GembaWalk::create($data);
        $walk->load('creator');

        return response()->json(new GembaWalkResource($walk), 201);
    }

    public function show(int $id): JsonResponse
    {
        $walk = GembaWalk::with('creator')->findOrFail($id);
        return response()->json(new GembaWalkResource($walk));
    }

    public function update(UpdateGembaWalkRequest $request, int $id): JsonResponse
    {
        $walk = GembaWalk::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\TenantFileService::class)->store($request->file('image'), 'gemba');
        } elseif ($request->input('remove_image')) {
            $data['image'] = null;
        }

        $walk->update($data);
        $walk->load('creator');

        return response()->json(new GembaWalkResource($walk));
    }

    public function destroy(int $id): JsonResponse
    {
        GembaWalk::findOrFail($id)->delete();
        return response()->json(['message' => 'Gemba Walk supprimé.']);
    }

    public function resolve(int $id): JsonResponse
    {
        $walk = GembaWalk::findOrFail($id);
        $walk->update(['status' => 'resolved']);
        return response()->json(new GembaWalkResource($walk));
    }

    public function stats(): JsonResponse
    {
        $base = GembaWalk::query();

        return response()->json([
            'total'         => (clone $base)->count(),
            'open'          => (clone $base)->where('status', 'open')->count(),
            'in_progress'   => (clone $base)->where('status', 'in_progress')->count(),
            'resolved'      => (clone $base)->where('status', 'resolved')->count(),
            'overdue'       => (clone $base)->where('status', '!=', 'resolved')
                                  ->whereNotNull('due_date')
                                  ->whereDate('due_date', '<', now())->count(),
            'high_priority' => (clone $base)->where('status', '!=', 'resolved')
                                  ->where('priority', 'high')->count(),
        ]);
    }
}
