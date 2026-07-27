<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SafetyIncident;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyTrackerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', (int) now()->year);

        $lastLti = SafetyIncident::query()
            ->whereIn('type', ['LTI', 'FA', 'FAT'])
            ->whereNull('deleted_at')
            ->orderByDesc('date')
            ->first();

        $startDate   = $lastLti ? Carbon::parse($lastLti->date)->addDay() : now()->startOfYear();
        $daysWithout = max(0, (int) $startDate->diffInDays(now()));
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
