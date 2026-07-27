<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visitor\StoreVisitorRequest;
use App\Http\Resources\VisitorResource;
use App\Models\Visitor;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = Visitor::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('nom',          'ilike', "%{$s}%")
                ->orWhere('prenom',     'ilike', "%{$s}%")
                ->orWhere('entreprise', 'ilike', "%{$s}%")
                ->orWhere('badge_number','ilike', "%{$s}%")
            );
        }

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from'))   $query->whereDate('checked_in_at', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('checked_in_at', '<=', $request->to);

        $result = $this->paginateQuery($query->orderByDesc('checked_in_at'), $request);
        $result['data'] = VisitorResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreVisitorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by']    = $request->user()->id;
        $data['status']        = 'in';
        $data['checked_in_at'] = now();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('operix/visitors', 'public');
        }

        $visitor = Visitor::create($data);

        return response()->json(new VisitorResource($visitor), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(new VisitorResource(Visitor::findOrFail($id)));
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $visitor = Visitor::findOrFail($id);

        if ($visitor->status === 'out') {
            return response()->json(['message' => 'Ce visiteur a déjà quitté les lieux.'], 422);
        }

        $visitor->update([
            'status'         => 'out',
            'checked_out_at' => now(),
        ]);

        return response()->json(new VisitorResource($visitor));
    }

    public function destroy(int $id): JsonResponse
    {
        Visitor::findOrFail($id)->delete();
        return response()->json(['message' => 'Visite supprimée.']);
    }

    public function onSite(): JsonResponse
    {
        $visitors = Visitor::where('status', 'in')
            ->orderByDesc('checked_in_at')
            ->get();

        return response()->json([
            'count' => $visitors->count(),
            'data'  => VisitorResource::collection($visitors),
        ]);
    }
}
