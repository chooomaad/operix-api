<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Traits\HandlesApiResources;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyTrackerController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', (int) now()->year);

        $lastLti = SafetyIncident::query()
            ->whereIn('type', ['LTI', 'FA', 'FAT'])
            ->whereNull('deleted_at')
            ->orderByDesc('date')
            ->first();

        // Date de départ du compteur = la PLUS RÉCENTE parmi :
        //  - le lendemain du dernier accident avec arrêt (LTI) → remise à zéro
        //    automatique dès le jour de déclaration ;
        //  - la date de référence fixée par un administrateur (« Remettre à 0 »),
        //    stockée dans tenant.settings['safety_tracker_start_date'].
        // À défaut de l'un et de l'autre, on part du début de l'année.
        $baseline = data_get($request->user()?->tenant?->settings, 'safety_tracker_start_date');

        $startDate = null;
        if ($lastLti) {
            $startDate = Carbon::parse($lastLti->date)->addDay();
        }
        if ($baseline) {
            $b = Carbon::parse($baseline);
            $startDate = ($startDate === null || $b->gt($startDate)) ? $b : $startDate;
        }
        if ($startDate === null) {
            $startDate = now()->startOfYear();
        }

        $daysWithout = max(0, (int) $startDate->startOfDay()->diffInDays(now()));
        $bestStreak  = $this->computeBestStreak();

        $incidents = SafetyIncident::query()
            ->whereYear('date', $year)
            ->selectRaw("TO_CHAR(date, 'MM') as month, COUNT(*) as total, SUM(CASE WHEN type='LTI' THEN 1 ELSE 0 END) as lti")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyData = [];
        foreach (range(1, 12) as $m) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $monthlyData[$key] = [
                'incidents' => (int) ($incidents[$key]->total ?? 0),
                'lti'       => (int) ($incidents[$key]->lti   ?? 0),
            ];
        }

        $empCount = Employee::where('is_active', true)->count();
        $ltiYtd   = SafetyIncident::query()->whereYear('date', $year)->where('type', 'LTI')->count();
        $totalYtd = SafetyIncident::query()->whereYear('date', $year)->count();
        $tf       = $empCount > 0 ? round(($ltiYtd * 1_000_000) / ($empCount * 200 * 8), 2) : 0;
        $tg       = $empCount > 0 ? round(($totalYtd * 1_000) / ($empCount * 200 * 8), 2) : 0;

        return response()->json([
            'days_without_accident' => $daysWithout,
            'last_incident'         => $lastLti ? [
                'id'        => $lastLti->id,
                'reference' => $lastLti->reference,
                'date'      => $lastLti->date?->format('Y-m-d'),
                'type'      => $lastLti->type,
                'severity'  => $lastLti->severity,
                'location'  => $lastLti->location,
            ] : null,
            'streak_start'          => $startDate->format('Y-m-d'),
            'best_streak_days'      => $bestStreak,
            'year'                  => $year,
            'monthly_data'          => $monthlyData,
            'kpis'                  => [
                'total_incidents_ytd' => $totalYtd,
                'lti_ytd'             => $ltiYtd,
                'taux_frequence'      => $tf,
                'taux_gravite'        => $tg,
                'employee_count'      => $empCount,
            ],
        ]);
    }

    /**
     * Remet le compteur « jours sans accident » à zéro à partir d'une date de
     * référence (aujourd'hui par défaut), enregistrée dans tenant.settings.
     *
     * N'efface AUCUNE donnée : les incidents restent intacts. Un LTI déclaré APRÈS
     * cette date réinitialisera de nouveau le compteur automatiquement.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $date   = $validated['date'] ?? now()->toDateString();
        $tenant = $request->user()->tenant;

        $settings = $tenant->settings ?? [];
        $old      = $settings['safety_tracker_start_date'] ?? null;
        $settings['safety_tracker_start_date'] = $date;
        $tenant->update(['settings' => $settings]);

        $this->auditLog(
            $request,
            'safety_tracker_reset',
            Tenant::class,
            $tenant->id,
            ['safety_tracker_start_date' => $old],
            ['safety_tracker_start_date' => $date],
        );

        return $this->index($request);
    }

    public function history(): JsonResponse
    {
        $incidents = SafetyIncident::query()
            ->whereIn('type', ['LTI', 'FA', 'FAT'])
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->get(['id', 'reference', 'date', 'type', 'location']);

        $streaks = [];
        $prev    = null;

        foreach ($incidents as $inc) {
            $current = Carbon::parse($inc->date);
            if ($prev) {
                $streaks[] = [
                    'from'     => $prev->format('Y-m-d'),
                    'to'       => $current->subDay()->format('Y-m-d'),
                    'days'     => (int) $prev->diffInDays($current),
                    'ended_by' => $inc->reference,
                ];
            }
            $prev = $current->addDay();
        }

        if ($prev) {
            $streaks[] = [
                'from'     => $prev->format('Y-m-d'),
                'to'       => now()->format('Y-m-d'),
                'days'     => (int) $prev->diffInDays(now()),
                'ended_by' => null,
            ];
        }

        return response()->json([
            'streaks'   => array_reverse($streaks),
            'incidents' => $incidents,
        ]);
    }

    private function computeBestStreak(): int
    {
        $incidents = SafetyIncident::query()
            ->whereIn('type', ['LTI', 'FA', 'FAT'])
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->pluck('date');

        if ($incidents->isEmpty()) {
            return (int) now()->startOfYear()->diffInDays(now());
        }

        $best = 0;
        $prev = null;

        foreach ($incidents as $date) {
            $current = Carbon::parse($date);
            if ($prev) {
                $best = max($best, (int) $prev->diffInDays($current));
            }
            $prev = $current;
        }

        if ($prev) {
            $best = max($best, (int) $prev->diffInDays(now()));
        }

        return $best;
    }
}
