<?php

namespace App\Http\Controllers\Api;

use App\Events\HseEventCreated;
use App\Http\Controllers\Controller;
use App\Support\HseEventPayload;
use App\Http\Requests\NearMiss\StoreNearMissRequest;
use App\Http\Requests\NearMiss\UpdateNearMissRequest;
use App\Http\Resources\NearMissResource;
use App\Models\SafetyNearMiss;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NearMissController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = SafetyNearMiss::query()->with('reporter');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('description', 'ilike', "%{$s}%")
                ->orWhere('location',  'ilike', "%{$s}%")
            );
        }

        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('status'))   $query->where('status',   $request->status);
        if ($request->filled('from'))     $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))       $query->whereDate('date', '<=', $request->to);

        $result = $this->paginateQuery($query->orderByDesc('date'), $request);
        $result['data'] = NearMissResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreNearMissRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['reported_by'] = $request->user()->id;
        if ($request->hasFile('image')) {
            $data['image'] = app(\App\Services\TenantFileService::class)->store($request->file('image'), 'near-miss');
        }

        $nearMiss = $this->createWithReference('NM', SafetyNearMiss::class, $data);
        $nearMiss->load('reporter');

        // Diffusion temps reel. L'evenement implemente ShouldBroadcast :
        // il part par la file d'attente, la reponse HTTP n'attend donc ni le
        // serveur WebSocket ni les clients connectes.
        HseEventCreated::dispatch(HseEventPayload::fromModel($nearMiss));

        return response()->json(new NearMissResource($nearMiss), 201);
    }

    public function show(int $id): JsonResponse
    {
        $nearMiss = SafetyNearMiss::with('reporter')->findOrFail($id);
        return response()->json(new NearMissResource($nearMiss));
    }

    public function update(UpdateNearMissRequest $request, int $id): JsonResponse
    {
        $nearMiss = SafetyNearMiss::findOrFail($id);
        $nearMiss->update($request->validated());
        $nearMiss->load('reporter');

        return response()->json(new NearMissResource($nearMiss));
    }

    public function destroy(int $id): JsonResponse
    {
        SafetyNearMiss::findOrFail($id)->delete();
        return response()->json(['message' => 'Near miss supprimé.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'corrective_action' => ['required', 'string'],
        ]);

        $nearMiss = SafetyNearMiss::findOrFail($id);
        $nearMiss->update([
            'status'            => 'closed',
            'corrective_action' => $request->corrective_action,
        ]);

        return response()->json(new NearMissResource($nearMiss));
    }
}
