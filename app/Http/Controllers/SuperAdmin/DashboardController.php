<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Employee;
use App\Models\Breach;
use App\Models\SafetyNearMiss;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::withCount(['users', 'employees'])->get();

        return response()->json([
            'platform' => [
                'total_tenants'   => $tenants->count(),
                'active_tenants'  => $tenants->where('status', 'active')->count(),
                'trial_tenants'   => $tenants->where('status', 'trial')->count(),
                'suspended'       => $tenants->where('status', 'suspended')->count(),
                'total_users'     => User::whereNotNull('tenant_id')->count(),
                'total_employees' => Employee::count(),
            ],
            'by_plan' => $tenants->groupBy('plan')
                ->map(fn($group) => $group->count()),
            'tenants' => $tenants->map(fn($t) => [
                'id'             => $t->id,
                'name'           => $t->name,
                'slug'           => $t->slug,
                'plan'           => $t->plan,
                'status'         => $t->status,
                'users_count'    => $t->users_count,
                'employees_count'=> $t->employees_count,
                'created_at'     => $t->created_at,
            ]),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', date('Y'));

        return response()->json([
            'incidents_this_year'  => SafetyIncident::whereYear('date', $year)->count(),
            'near_miss_this_year'  => SafetyNearMiss::whereYear('date', $year)->count(),
            'breaches_this_year'   => Breach::whereYear('date', $year)->count(),
            'new_tenants_this_year'=> Tenant::whereYear('created_at', $year)->count(),
            'new_users_this_year'  => User::whereNotNull('tenant_id')
                                          ->whereYear('created_at', $year)->count(),
            'incidents_by_tenant'  => SafetyIncident::whereYear('date', $year)
                ->selectRaw('tenant_id, COUNT(*) as total')
                ->groupBy('tenant_id')
                ->with('tenant:id,name')
                ->get()
                ->map(fn($r) => [
                    'tenant' => $r->tenant?->name ?? 'N/A',
                    'total'  => $r->total,
                ]),
            'tenants_by_month' => Tenant::whereYear('created_at', $year)
                ->selectRaw("TO_CHAR(created_at, 'MM') as month, COUNT(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month'),
        ]);
    }
}
