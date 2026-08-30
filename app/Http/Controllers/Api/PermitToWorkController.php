<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermitToWork;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermitToWorkController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = PermitToWork::query()
            ->with(['contractor:id,company_name', 'requestedBy:id,name', 'approvedBy:id,name']);

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('type'))     $query->where('type',   $request->type);
        if ($request->filled('from'))     $query->whereDate('valid_from', '>=', $request->from);
        if ($request->filled('to'))       $query->whereDate('valid_to',   '<=', $request->to);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('title',      'ilike', "%{$s}%")
                ->orWhere('reference','ilike', "%{$s}%")
                ->orWhere('location', 'ilike', "%{$s}%")
            );
        }

        $result = $this->paginateQuery($query->orderByDesc('valid_from'), $request);

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'           => ['required', 'in:hot_work,confined_space,electrical,working_at_height,excavation,chemical,general'],
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string'],
            'location'       => ['required', 'string', 'max:255'],
            'contractor_id'  => ['nullable', 'integer'],
            'contractor_name'=> ['nullable', 'string', 'max:255'],
            'valid_from'     => ['required', 'date'],
            'valid_to'       => ['required', 'date', 'after:valid_from'],
            'risks'          => ['nullable', 'array'],
            'precautions'    => ['nullable', 'array'],
            'ppe_required'   => ['nullable', 'array'],
            'workers'        => ['nullable', 'array'],
        ]);

        $permit = $this->createWithReference('PTW', PermitToWork::class, [
            ...$validated,
            'requested_by' => $request->user()->id,
            'status'       => 'draft',
        ]);

        $permit->load(['contractor:id,company_name', 'requestedBy:id,name']);
        $this->auditLog($request, 'permit_created', PermitToWork::class, $permit->id, null, $permit->toArray());

        return response()->json($permit, 201);
    }

    public function show(int $id): JsonResponse
    {
        $permit = PermitToWork::with([
            'contractor:id,company_name',
            'requestedBy:id,name',
            'approvedBy:id,name',
        ])->findOrFail($id);

        return response()->json($permit);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permit = PermitToWork::findOrFail($id);

        abort_if(in_array($permit->status, ['approved', 'active', 'closed']), 422,
            'Un permis approuvé ou actif ne peut plus être modifié.');

        $validated = $request->validate([
            'type'           => ['sometimes', 'in:hot_work,confined_space,electrical,working_at_height,excavation,chemical,general'],
            'title'          => ['sometimes', 'string', 'max:255'],
            'description'    => ['sometimes', 'string'],
            'location'       => ['sometimes', 'string', 'max:255'],
            'contractor_id'  => ['nullable', 'integer'],
            'contractor_name'=> ['nullable', 'string', 'max:255'],
            'valid_from'     => ['sometimes', 'date'],
            'valid_to'       => ['sometimes', 'date'],
            'risks'          => ['nullable', 'array'],
            'precautions'    => ['nullable', 'array'],
            'ppe_required'   => ['nullable', 'array'],
            'workers'        => ['nullable', 'array'],
        ]);

        $old = $permit->toArray();
        $permit->update($validated);
        $this->auditLog($request, 'permit_updated', PermitToWork::class, $permit->id, $old, $permit->fresh()->toArray());

        return response()->json($permit->load([
            'contractor:id,company_name',
            'requestedBy:id,name',
            'approvedBy:id,name',
        ]));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $permit = PermitToWork::findOrFail($id);
        abort_if($permit->status === 'active', 422, 'Un permis actif ne peut pas être supprimé.');

        $this->auditLog($request, 'permit_deleted', PermitToWork::class, $permit->id, $permit->toArray(), null);
        $permit->delete();

        return response()->json(['message' => 'Permis supprimé.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'approval_notes' => ['nullable', 'string'],
        ]);

        $permit = PermitToWork::findOrFail($id);
        abort_if($permit->status !== 'pending', 422, 'Seul un permis en attente peut être approuvé.');

        $permit->update([
            'status'         => 'approved',
            'approved_by'    => $request->user()->id,
            'approved_at'    => now(),
            'approval_notes' => $request->approval_notes,
        ]);

        $this->auditLog($request, 'permit_approved', PermitToWork::class, $permit->id, null, null);

        return response()->json($permit->load([
            'contractor:id,company_name',
            'requestedBy:id,name',
            'approvedBy:id,name',
        ]));
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'closure_notes' => ['required', 'string'],
        ]);

        $permit = PermitToWork::findOrFail($id);
        abort_if(! in_array($permit->status, ['approved', 'active']), 422,
            'Seul un permis approuvé ou actif peut être clôturé.');

        $permit->update([
            'status'        => 'closed',
            'closure_notes' => $request->closure_notes,
            'closed_at'     => now(),
        ]);

        $this->auditLog($request, 'permit_closed', PermitToWork::class, $permit->id, null, null);

        return response()->json($permit);
    }

    public function stats(Request $request): JsonResponse
    {
        $base = PermitToWork::query();

        return response()->json([
            'total'    => (clone $base)->count(),
            'draft'    => (clone $base)->where('status', 'draft')->count(),
            'pending'  => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'active'   => (clone $base)->where('status', 'active')->count(),
            'closed'   => (clone $base)->where('status', 'closed')->count(),
            'by_type'  => (clone $base)->selectRaw('type, COUNT(*) as total')
                             ->groupBy('type')->pluck('total', 'type'),
        ]);
    }
}
