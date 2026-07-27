<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Breach\StoreBreachRequest;
use App\Http\Requests\Breach\UpdateBreachRequest;
use App\Http\Resources\BreachResource;
use App\Models\Breach;
use App\Traits\HasTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreachController extends Controller
{
    use HasTenantScope;

    public function index(Request $request): JsonResponse
    {
        $query = Breach::query()->with(['employee', 'creator']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('description', 'ilike', "%{$s}%")
                ->orWhereHas('employee', fn($eq) => $eq
                    ->where('nom',       'ilike', "%{$s}%")
                    ->orWhere('prenom',   'ilike', "%{$s}%")
                    ->orWhere('matricule','ilike', "%{$s}%")
                )
            );
        }

        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('type'))        $query->where('type',        $request->type);
        if ($request->filled('severity'))    $query->where('severity',    $request->severity);
        if ($request->filled('status'))      $query->where('status',      $request->status);
        if ($request->filled('from'))        $query->whereDate('date',    '>=', $request->from);
        if ($request->filled('to'))          $query->whereDate('date',    '<=', $request->to);

        $result = $this->paginateQuery($query->orderByDesc('date'), $request);
        $result['data'] = BreachResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreBreachRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['reference']  = $this->generateReference('INF', Breach::class);

        $breach = Breach::create($data);
        $breach->load(['employee', 'creator']);

        return response()->json(new BreachResource($breach), 201);
    }

    public function show(int $id): JsonResponse
    {
        $breach = Breach::with(['employee', 'creator'])->findOrFail($id);
        return response()->json(new BreachResource($breach));
    }

    public function update(UpdateBreachRequest $request, int $id): JsonResponse
    {
        $breach = Breach::findOrFail($id);
        $breach->update($request->validated());
        $breach->load(['employee', 'creator']);

        return response()->json(new BreachResource($breach));
    }

    public function destroy(int $id): JsonResponse
    {
        Breach::findOrFail($id)->delete();
        return response()->json(['message' => 'Infraction supprimée.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'corrective_action' => ['required', 'string'],
        ]);

        $breach = Breach::findOrFail($id);
        $breach->update([
            'status'            => 'closed',
            'corrective_action' => $request->corrective_action,
        ]);

        return response()->json(new BreachResource($breach));
    }
}
