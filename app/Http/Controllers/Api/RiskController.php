<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Risk\StoreRiskRequest;
use App\Http\Requests\Risk\UpdateRiskRequest;
use App\Http\Resources\RiskResource;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = Risk::query()->with(['owner:id,name', 'department:id,name'])->withCount('actions');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('danger', 'ilike', "%{$s}%")
                ->orWhere('risk_description', 'ilike', "%{$s}%")
                ->orWhere('location', 'ilike', "%{$s}%")
                ->orWhere('reference', 'ilike', "%{$s}%")
            );
        }

        if ($request->filled('category'))        $query->where('category', $request->category);
        if ($request->filled('level'))           $query->where('level', $request->level);
        if ($request->filled('status'))          $query->where('status', $request->status);
        if ($request->filled('assessment_type')) $query->where('assessment_type', $request->assessment_type);
        if ($request->filled('department_id'))   $query->where('department_id', $request->integer('department_id'));
        // Risques à ré-évaluer (échéance de revue atteinte).
        if ($request->boolean('to_review'))      $query->whereNotNull('review_date')->whereDate('review_date', '<=', now());

        // Tri par défaut : les risques les plus élevés d'abord.
        $result = $this->paginateQuery($query->orderByDesc('score')->orderByDesc('date_identification'), $request);
        $result['data'] = RiskResource::collection($result['data']);

        return response()->json($result);
    }

    /**
     * Utilisateurs assignables comme responsable d'un risque ou d'une action
     * (id + nom uniquement — aucune donnée personnelle). Sert les listes du formulaire.
     */
    public function assignees(): JsonResponse
    {
        return response()->json(
            \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(StoreRiskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $risk = $this->createWithReference('RM', Risk::class, $data);
        $risk->load(['owner:id,name', 'department:id,name']);

        return response()->json(new RiskResource($risk), 201);
    }

    public function show(int $id): JsonResponse
    {
        $risk = Risk::with([
            'owner:id,name', 'department:id,name',
            'actions.responsible:id,name', 'actions.validator:id,name',
        ])->findOrFail($id);

        return response()->json(new RiskResource($risk));
    }

    public function update(UpdateRiskRequest $request, int $id): JsonResponse
    {
        $risk = Risk::findOrFail($id);
        $risk->update($request->validated());
        $risk->load(['owner:id,name', 'department:id,name']);

        return response()->json(new RiskResource($risk));
    }

    public function destroy(int $id): JsonResponse
    {
        Risk::findOrFail($id)->delete();
        return response()->json(['message' => 'Risque supprimé.']);
    }

    /**
     * Tableau de bord des risques : répartition par niveau, matrice 5×5, risques
     * critiques ouverts, actions en retard, risques à ré-évaluer, responsables en
     * retard, évolution par mois, répartition par catégorie.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->year);
        $base = Risk::query();

        $byLevel = (clone $base)->selectRaw('level, COUNT(*) as total')->groupBy('level')->pluck('total', 'level');
        $levels  = [
            'low'      => (int) ($byLevel['low'] ?? 0),
            'medium'   => (int) ($byLevel['medium'] ?? 0),
            'high'     => (int) ($byLevel['high'] ?? 0),
            'critical' => (int) ($byLevel['critical'] ?? 0),
        ];

        // Matrice 5×5 : nombre de risques par (probabilité, gravité).
        $cells = (clone $base)->selectRaw('probability, severity, COUNT(*) as total')
            ->groupBy('probability', 'severity')->get();
        $matrix = [];
        foreach (range(1, 5) as $p) {
            foreach (range(1, 5) as $s) {
                $matrix["{$p}_{$s}"] = 0;
            }
        }
        foreach ($cells as $c) {
            $matrix["{$c->probability}_{$c->severity}"] = (int) $c->total;
        }

        $criticalOpen = (clone $base)->where('level', 'critical')->where('status', '!=', 'closed')->count();
        $toReview     = (clone $base)->whereNotNull('review_date')->whereDate('review_date', '<=', now())
            ->where('status', '!=', 'closed')->count();

        $overdueActions = RiskAction::query()->where('status', '!=', 'done')
            ->whereNotNull('due_date')->whereDate('due_date', '<', now())->count();

        // Responsables ayant des actions en retard.
        $responsiblesOverdue = RiskAction::query()
            ->where('risk_actions.status', '!=', 'done')
            ->whereNotNull('due_date')->whereDate('due_date', '<', now())
            ->whereNotNull('responsible_id')
            ->join('users', 'risk_actions.responsible_id', '=', 'users.id')
            ->selectRaw('users.id, users.name, COUNT(*) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')->limit(8)->get();

        $byCategory = (clone $base)->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->orderByDesc('total')->pluck('total', 'category');

        $byMonth = (clone $base)->whereYear('date_identification', $year)
            ->selectRaw("TO_CHAR(date_identification,'MM') as month, COUNT(*) as total")
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $byZone = (clone $base)->whereNotNull('location')->where('location', '!=', '')
            ->selectRaw('location, COUNT(*) as total')
            ->groupBy('location')->orderByDesc('total')->limit(8)->pluck('total', 'location');

        return response()->json([
            'total'                => (clone $base)->count(),
            'open'                 => (clone $base)->where('status', 'open')->count(),
            'closed'               => (clone $base)->where('status', 'closed')->count(),
            'by_level'             => $levels,
            'matrix'               => $matrix,
            'critical_open'        => $criticalOpen,
            'overdue_actions'      => $overdueActions,
            'to_review'            => $toReview,
            'responsibles_overdue' => $responsiblesOverdue,
            'by_category'          => $byCategory,
            'by_month'             => $byMonth,
            'by_zone'              => $byZone,
            'year'                 => $year,
        ]);
    }
}
