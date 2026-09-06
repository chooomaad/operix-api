<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Risk\StoreRiskActionRequest;
use App\Http\Resources\RiskActionResource;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Services\TenantFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskActionController extends Controller
{
    /** Le risque parent est résolu dans le tenant courant (global scope). */
    private function risk(int $riskId): Risk
    {
        return Risk::findOrFail($riskId);
    }

    public function index(int $riskId): JsonResponse
    {
        $this->risk($riskId);
        $actions = RiskAction::where('risk_id', $riskId)
            ->with(['responsible:id,name', 'validator:id,name'])
            ->orderByDesc('created_at')->get();

        return response()->json(RiskActionResource::collection($actions));
    }

    public function store(StoreRiskActionRequest $request, int $riskId): JsonResponse
    {
        $this->risk($riskId);
        $data = $request->validated();
        $data['risk_id']    = $riskId;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('proof')) {
            $data['proof'] = app(TenantFileService::class)->store($request->file('proof'), 'risk-actions');
        }
        // Clôture automatique de la date quand l'action passe à « terminé ».
        if (($data['status'] ?? null) === 'done' && empty($data['closed_at'])) {
            $data['closed_at'] = now()->toDateString();
        }

        $action = RiskAction::create($data);
        $action->load(['responsible:id,name', 'validator:id,name']);

        return response()->json(new RiskActionResource($action), 201);
    }

    public function update(StoreRiskActionRequest $request, int $riskId, int $actionId): JsonResponse
    {
        $this->risk($riskId);
        $action = RiskAction::where('risk_id', $riskId)->findOrFail($actionId);
        $data = $request->validated();

        if ($request->hasFile('proof')) {
            $data['proof'] = app(TenantFileService::class)->replace($action->proof, $request->file('proof'), 'risk-actions');
        }
        if (($data['status'] ?? $action->status) === 'done' && empty($data['closed_at']) && ! $action->closed_at) {
            $data['closed_at'] = now()->toDateString();
        }

        $action->update($data);
        $action->load(['responsible:id,name', 'validator:id,name']);

        return response()->json(new RiskActionResource($action));
    }

    /** Validation HSE de l'action (réservée par permission risks.validate). */
    public function validateAction(Request $request, int $riskId, int $actionId): JsonResponse
    {
        $this->risk($riskId);
        $action = RiskAction::where('risk_id', $riskId)->findOrFail($actionId);
        $action->update([
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'status'       => 'done',
            'closed_at'    => $action->closed_at ?? now()->toDateString(),
        ]);
        $action->load(['responsible:id,name', 'validator:id,name']);

        return response()->json(new RiskActionResource($action));
    }

    public function destroy(int $riskId, int $actionId): JsonResponse
    {
        $this->risk($riskId);
        RiskAction::where('risk_id', $riskId)->findOrFail($actionId)->delete();
        return response()->json(['message' => 'Action supprimée.']);
    }
}
