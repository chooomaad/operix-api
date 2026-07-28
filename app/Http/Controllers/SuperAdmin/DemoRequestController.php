<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des demandes de démo par l'équipe Operix (super_admin).
 * La conversion en tenant trial est traitée séparément (workflow de provisioning).
 */
class DemoRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DemoRequest::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('company_name', 'ilike', "%{$s}%")
                ->orWhere('email', 'ilike', "%{$s}%")
                ->orWhere('reference', 'ilike', "%{$s}%"));
        }

        return response()->json($query->paginate(min((int) $request->get('per_page', 25), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(DemoRequest::with('tenant:id,name,slug,status')->findOrFail($id));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'approved', 'rejected'])],
        ]);

        $demo = DemoRequest::findOrFail($id);

        // Une demande déjà convertie n'est plus modifiable via ce canal.
        abort_if($demo->status === 'converted', 422, 'Cette demande a déjà été convertie en tenant.');

        $demo->update([
            'status'     => $validated['status'],
            'handled_by' => $request->user()->id,
        ]);

        return response()->json($demo);
    }
}
