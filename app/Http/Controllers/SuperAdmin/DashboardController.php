<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Breach;
use App\Models\DemoRequest;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard plateforme Super Admin (cross-tenant).
 *
 * Les lectures cross-tenant sur des modèles tenant-scopés passent par le bypass EXPLICITE
 * et localisé TenantContext::runWithoutScope() — jamais par un bypass global automatique.
 */
class DashboardController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $data = $context->runWithoutScope(function () {
            $tenants = Tenant::withCount(['users', 'employees'])->get();

            return [
                'platform' => [
                    'total_tenants'   => $tenants->count(),
                    'active_tenants'  => $tenants->where('status', 'active')->count(),
                    'trial_tenants'   => $tenants->where('status', 'trial')->count(),
                    'suspended'       => $tenants->where('status', 'suspended')->count(),
                    'total_users'     => User::whereNotNull('tenant_id')->count(),
                    'total_employees' => Employee::count(),
                ],
                'commercial' => [
                    'total_orders'         => Order::count(),
                    'paid_orders'          => Order::where('status', 'paid')->count(),
                    'succeeded_payments'   => Payment::where('status', 'succeeded')->count(),
                    'active_subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
                    'new_demo_requests'    => DemoRequest::where('status', 'new')->count(),
                    'recent_orders'        => Order::latest()->limit(5)
                        ->get(['id', 'reference', 'company_name', 'amount', 'currency', 'status', 'created_at']),
                    'recent_payments'      => Payment::latest()->limit(5)
                        ->get(['id', 'order_id', 'provider', 'amount', 'currency', 'status', 'created_at']),
                ],
                'by_plan' => $tenants->groupBy('plan')->map(fn ($group) => $group->count()),
                'tenants' => $tenants->map(fn ($t) => [
                    'id'              => $t->id,
                    'name'            => $t->name,
                    'slug'            => $t->slug,
                    'plan'            => $t->plan,
                    'status'          => $t->status,
                    'users_count'     => $t->users_count,
                    'employees_count' => $t->employees_count,
                    'created_at'      => $t->created_at,
                ]),
            ];
        });

        return response()->json($data);
    }

    public function stats(Request $request, TenantContext $context): JsonResponse
    {
        $year = (int) $request->get('year', date('Y'));

        $data = $context->runWithoutScope(fn () => [
            'incidents_this_year'   => SafetyIncident::whereYear('date', $year)->count(),
            'near_miss_this_year'   => SafetyNearMiss::whereYear('date', $year)->count(),
            'breaches_this_year'    => Breach::whereYear('date', $year)->count(),
            'new_tenants_this_year' => Tenant::whereYear('created_at', $year)->count(),
            'new_users_this_year'   => User::whereNotNull('tenant_id')->whereYear('created_at', $year)->count(),
            'incidents_by_tenant'   => SafetyIncident::whereYear('date', $year)
                ->selectRaw('tenant_id, COUNT(*) as total')
                ->groupBy('tenant_id')
                ->with('tenant:id,name')
                ->get()
                ->map(fn ($r) => ['tenant' => $r->tenant?->name ?? 'N/A', 'total' => $r->total]),
            'tenants_by_month'      => Tenant::whereYear('created_at', $year)
                ->selectRaw("TO_CHAR(created_at, 'MM') as month, COUNT(*) as total")
                ->groupBy('month')->orderBy('month')->pluck('total', 'month'),
        ]);

        return response()->json($data);
    }
}
