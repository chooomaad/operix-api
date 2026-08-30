<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Breach;
use App\Models\Contractor;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EnvironmentReport;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\PermitToWork;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now   = now();
        $month = $now->month;
        $year  = $now->year;

        return response()->json([
            'employees'    => $this->employeeKpis($month, $year),
            'safety'       => $this->safetyKpis($month, $year),
            'environment'  => $this->environmentKpis($month, $year),
            'contractors'  => $this->contractorKpis(),
            'equipment'    => $this->equipmentKpis(),
            'visitors'     => $this->visitorKpis(),
            'generated_at' => $now->toIso8601String(),
        ]);
    }

    public function recentActivity(): JsonResponse
    {
        $incidents = SafetyIncident::latest('created_at')->limit(5)
            ->get(['id', 'reference', 'type', 'severity', 'status', 'location', 'date', 'created_at'])
            ->map(fn($i) => [
                'id'         => $i->id,
                'type'       => 'incident',
                'icon'       => 'fire',
                'title'      => $i->reference . ' — ' . $i->type,
                'subtitle'   => $i->location ?? '',
                'severity'   => $i->severity,
                'status'     => $i->status,
                'created_at' => $i->created_at?->toIso8601String(),
                'link'       => '/incidents/' . $i->id,
            ]);

        $nearMiss = SafetyNearMiss::latest('created_at')->limit(5)
            ->get(['id', 'reference', 'severity', 'status', 'location', 'date', 'created_at'])
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => 'near_miss',
                'icon'       => 'warning',
                'title'      => $n->reference . ' — Presqu\'accident',
                'subtitle'   => $n->location ?? '',
                'severity'   => $n->severity,
                'status'     => $n->status,
                'created_at' => $n->created_at?->toIso8601String(),
                'link'       => '/near-miss/' . $n->id,
            ]);

        $breaches = Breach::latest('created_at')->limit(5)
            ->get(['id', 'reference', 'type', 'severity', 'status', 'location', 'created_at'])
            ->map(fn($b) => [
                'id'         => $b->id,
                'type'       => 'breach',
                'icon'       => 'shield',
                'title'      => $b->reference . ' — ' . $b->type,
                'subtitle'   => $b->location ?? '',
                'severity'   => $b->severity,
                'status'     => $b->status,
                'created_at' => $b->created_at?->toIso8601String(),
                'link'       => '/breaches/' . $b->id,
            ]);

        $visitors = Visitor::where('status', 'in')->latest('checked_in_at')->limit(5)
            ->get(['id', 'nom', 'prenom', 'entreprise', 'checked_in_at'])
            ->map(fn($v) => [
                'id'         => $v->id,
                'type'       => 'visitor',
                'icon'       => 'user',
                'title'      => $v->prenom . ' ' . $v->nom,
                'subtitle'   => $v->entreprise ?? 'Visiteur',
                'severity'   => null,
                'status'     => 'in',
                'created_at' => $v->checked_in_at?->toIso8601String(),
                'link'       => '/visitors',
            ]);

        $all = collect()
            ->merge($incidents)
            ->merge($nearMiss)
            ->merge($breaches)
            ->merge($visitors)
            ->sortByDesc('created_at')
            ->values()
            ->take(15);

        return response()->json(['activities' => $all]);
    }

    public function topZones(): JsonResponse
    {
        $incidents = SafetyIncident::query()
            ->whereNotNull('location')->where('location', '!=', '')
            ->selectRaw('location, COUNT(*) as total')
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'location');

        $nearMiss = SafetyNearMiss::query()
            ->whereNotNull('location')->where('location', '!=', '')
            ->selectRaw('location, COUNT(*) as total')
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'location');

        $breaches = Breach::query()
            ->whereNotNull('location')->where('location', '!=', '')
            ->selectRaw('location, COUNT(*) as total')
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'location');

        $allLocs = collect($incidents->keys())
            ->merge($nearMiss->keys())
            ->merge($breaches->keys())
            ->unique();

        $zones = $allLocs->map(fn($loc) => [
            'location'  => $loc,
            'incidents' => (int) ($incidents[$loc] ?? 0),
            'near_miss' => (int) ($nearMiss[$loc]  ?? 0),
            'breaches'  => (int) ($breaches[$loc]   ?? 0),
            'total'     => (int) ($incidents[$loc] ?? 0)
                         + (int) ($nearMiss[$loc]  ?? 0)
                         + (int) ($breaches[$loc]   ?? 0),
        ])->sortByDesc('total')->values()->take(8);

        return response()->json(['zones' => $zones]);
    }

    public function safetyTimeline(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->year);

        $incidents = SafetyIncident::query()
            ->whereYear('date', $year)
            ->selectRaw("TO_CHAR(date, 'MM') as month, COUNT(*) as total, severity")
            ->groupBy('month', 'severity')
            ->orderBy('month')
            ->get();

        $nearMiss = SafetyNearMiss::query()
            ->whereYear('date', $year)
            ->selectRaw("TO_CHAR(date, 'MM') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $months = [];
        foreach (range(1, 12) as $m) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $months[$key] = [
                'incidents' => 0,
                'near_miss' => (int) ($nearMiss[$key] ?? 0),
                'high'      => 0,
                'critical'  => 0,
            ];
        }

        foreach ($incidents as $row) {
            $months[$row->month]['incidents'] += (int) $row->total;
            if (in_array($row->severity, ['high', 'critical'])) {
                $months[$row->month][$row->severity] += (int) $row->total;
            }
        }

        return response()->json([
            'year'     => (int) $year,
            'timeline' => $months,
        ]);
    }

    public function employeeBreakdown(): JsonResponse
    {
        $byDept = Employee::query()
            ->where('is_active', true)
            ->whereNull('employees.deleted_at')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as department, COUNT(*) as total')
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->pluck('total', 'department');

        $byContract = Employee::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->selectRaw('type_contrat, COUNT(*) as total')
            ->groupBy('type_contrat')
            ->pluck('total', 'type_contrat');

        $byGender = Employee::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return response()->json([
            'by_department' => $byDept,
            'by_contract'   => $byContract,
            'by_gender'     => $byGender,
        ]);
    }

    public function incidentStats(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->year);
        $base = SafetyIncident::query()->whereYear('date', $year);

        $byType     = (clone $base)->selectRaw('type, COUNT(*) as total')
                          ->groupBy('type')->pluck('total', 'type');
        $bySeverity = (clone $base)->selectRaw('severity, COUNT(*) as total')
                          ->groupBy('severity')->pluck('total', 'severity');
        $byStatus   = (clone $base)->selectRaw('status, COUNT(*) as total')
                          ->groupBy('status')->pluck('total', 'status');
        $byMonth    = (clone $base)->selectRaw("TO_CHAR(date,'MM') as month, COUNT(*) as total")
                          ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $empCount = Employee::where('is_active', true)->count();
        $ltiCount = (clone $base)->where('type', 'LTI')->count();
        $tf       = $empCount > 0 ? round(($ltiCount * 1_000_000) / ($empCount * 200 * 8), 2) : 0;

        return response()->json([
            'year'           => (int) $year,
            'total'          => (clone $base)->count(),
            'by_type'        => $byType,
            'by_severity'    => $bySeverity,
            'by_status'      => $byStatus,
            'by_month'       => $byMonth,
            'taux_frequence' => $tf,
            'lti_count'      => $ltiCount,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function employeeKpis(int $month, int $year): array
    {
        $base = Employee::query()->whereNull('deleted_at');

        return [
            'total_actifs'      => (clone $base)->where('is_active', true)->count(),
            'total_inactifs'    => (clone $base)->where('is_active', false)->count(),
            'nouvelles_entrees' => (clone $base)->whereYear('date_embauche', $year)
                                     ->whereMonth('date_embauche', $month)->count(),
            'entrees_ytd'       => (clone $base)->whereYear('date_embauche', $year)->count(),
        ];
    }

    private function safetyKpis(int $month, int $year): array
    {
        $incBase = SafetyIncident::query();
        $nmBase  = SafetyNearMiss::query();
        $incMois = (clone $incBase)->whereYear('date', $year)->whereMonth('date', $month);
        $incYtd  = (clone $incBase)->whereYear('date', $year);
        $empCount = Employee::where('is_active', true)->count();
        $ltiYtd   = (clone $incYtd)->where('type', 'LTI')->count();
        $tf       = $empCount > 0 ? round(($ltiYtd * 1_000_000) / ($empCount * 200 * 8), 2) : 0;

        return [
            'incidents_mois'    => (clone $incMois)->count(),
            'incidents_ytd'     => (clone $incYtd)->count(),
            'incidents_ouverts' => (clone $incBase)->where('status', 'open')->count(),
            'near_miss_mois'    => (clone $nmBase)->whereYear('date', $year)->whereMonth('date', $month)->count(),
            'near_miss_ytd'     => (clone $nmBase)->whereYear('date', $year)->count(),
            'near_miss_ouverts' => (clone $nmBase)->where('status', 'open')->count(),
            'lti_ytd'           => $ltiYtd,
            'taux_frequence'    => $tf,
            'infractions_mois'  => Breach::query()->whereYear('date', $year)->whereMonth('date', $month)->count(),
        ];
    }

    private function environmentKpis(int $month, int $year): array
    {
        $base = EnvironmentReport::query();

        return [
            'rapports_mois'    => (clone $base)->whereYear('date', $year)->whereMonth('date', $month)->count(),
            'rapports_ytd'     => (clone $base)->whereYear('date', $year)->count(),
            'rapports_ouverts' => (clone $base)->where('status', 'open')->count(),
            'par_type'         => (clone $base)->whereYear('date', $year)
                                    ->selectRaw('type, COUNT(*) as total')
                                    ->groupBy('type')->pluck('total', 'type'),
        ];
    }


    private function contractorKpis(): array
    {
        $base = Contractor::query()->whereNull('deleted_at');

        return [
            'total_actifs'    => (clone $base)->where('status', 'active')->count(),
            'total_suspendus' => (clone $base)->where('status', 'suspended')->count(),
            'expires_30j'     => (clone $base)->where('status', 'active')
                                    ->whereNotNull('contract_end')
                                    ->whereDate('contract_end', '<=', now()->addDays(30))
                                    ->whereDate('contract_end', '>=', now())->count(),
        ];
    }

    private function equipmentKpis(): array
    {
        $base = Equipment::query()->whereNull('deleted_at');

        return [
            'total'            => (clone $base)->count(),
            'operationnels'    => (clone $base)->where('status', 'operational')->count(),
            'en_maintenance'   => (clone $base)->where('status', 'maintenance')->count(),
            'inspections_dues' => (clone $base)->whereNotNull('next_inspection')
                                     ->whereDate('next_inspection', '<=', now())->count(),
            'inspections_30j'  => (clone $base)->whereNotNull('next_inspection')
                                     ->whereDate('next_inspection', '>', now())
                                     ->whereDate('next_inspection', '<=', now()->addDays(30))->count(),
        ];
    }

    private function visitorKpis(): array
    {
        $base = Visitor::query();

        return [
            'presents'     => (clone $base)->where('status', 'in')->count(),
            'entrees_auj'  => (clone $base)->whereDate('checked_in_at', now())->count(),
            'entrees_mois' => (clone $base)->whereMonth('checked_in_at', now()->month)
                                 ->whereYear('checked_in_at', now()->year)->count(),
        ];
    }
}
