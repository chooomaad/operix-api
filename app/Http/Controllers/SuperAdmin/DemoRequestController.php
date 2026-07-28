<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\Plan;
use App\Services\ProvisioningService;
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

    /**
     * Convertit une demande de démo en environnement TRIAL.
     * Réutilise le ProvisioningService (aucune duplication avec le parcours payant),
     * idempotent : une demande déjà convertie ne recrée jamais de tenant.
     */
    public function convert(Request $request, int $id, ProvisioningService $provisioning): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug'  => ['required', 'string', 'exists:plans,slug'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $demo = DemoRequest::findOrFail($id);
        abort_if($demo->tenant_id !== null || $demo->status === 'converted', 422, 'Cette demande a déjà été convertie.');

        $plan   = Plan::where('slug', $validated['plan_slug'])->firstOrFail();
        $result = $provisioning->provisionTrialFromDemo($demo, $plan, $validated['trial_days'] ?? 14);

        $demo->update(['handled_by' => $request->user()->id]);

        if ($result->created && $result->activationToken) {
            app(\App\Services\CommercialNotifier::class)->activation($result->admin, $result->activationToken);
        }

        return response()->json([
            'tenant'          => $result->tenant->only('id', 'name', 'slug', 'status', 'demo_expires_at'),
            'admin'           => $result->admin->only('id', 'name', 'email'),
            'demo_request_id' => $demo->id,
        ], 201);
    }
}
