<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::withCount(['users', 'employees'])
            ->withTrashed($request->boolean('with_deleted'));

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('name',   'ilike', "%{$s}%")
                ->orWhere('slug', 'ilike', "%{$s}%")
            );
        }

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('plan'))   $query->where('plan',   $request->plan);

        $perPage   = min((int) $request->get('per_page', 25), 100);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'country'       => ['nullable', 'string', 'size:2'],
            'timezone'      => ['nullable', 'string'],
            'locale'        => ['nullable', 'string'],
            'plan'          => ['nullable', 'in:starter,pro,enterprise'],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'admin_name'    => ['required', 'string', 'max:255'],
            'admin_email'   => ['required', 'email', 'unique:users,email'],
        ]);

        $tenant = Tenant::create([
            'name'          => $validated['name'],
            'slug'          => Str::slug($validated['name']) . '-' . Str::random(4),
            'country'       => $validated['country'] ?? 'MR',
            'timezone'      => $validated['timezone'] ?? 'Africa/Nouakchott',
            'locale'        => $validated['locale'] ?? 'fr',
            'plan'          => $validated['plan'] ?? 'starter',
            'status'        => 'active',
            'max_employees' => $validated['max_employees'] ?? 100,
            'settings'      => [],
        ]);

        $admin = User::create([
            'name'      => $validated['admin_name'],
            'email'     => $validated['admin_email'],
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $tenant->loadCount(['users', 'employees']);

        return response()->json([
            'tenant' => $tenant,
            'admin'  => $admin->only('id', 'name', 'email', 'role'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::withCount(['users', 'employees'])
            ->withTrashed()
            ->findOrFail($id);

        $tenant->load(['users:id,name,email,role,is_active,tenant_id']);

        return response()->json($tenant);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'plan'          => ['sometimes', 'in:starter,pro,enterprise'],
            'status'        => ['sometimes', 'in:active,suspended,trial'],
            'max_employees' => ['sometimes', 'integer', 'min:1'],
            'timezone'      => ['sometimes', 'string'],
            'locale'        => ['sometimes', 'string'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings'      => ['sometimes', 'array'],
        ]);

        $tenant->update($validated);

        return response()->json($tenant->loadCount(['users', 'employees']));
    }

    public function destroy(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();

        return response()->json(['message' => "Tenant {$tenant->name} archivé."]);
    }

    public function suspend(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'suspended']);

        return response()->json(['message' => "Tenant {$tenant->name} suspendu."]);
    }

    public function activate(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'active']);

        return response()->json(['message' => "Tenant {$tenant->name} activé."]);
    }

    public function impersonate(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $admin  = User::where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->firstOrFail();

        $token = $admin->createToken(
            "superadmin-impersonate-{$request->user()->id}",
            ['*'],
            now()->addHours(2)
        );

        return response()->json([
            'token'  => $token->plainTextToken,
            'user'   => $admin->only('id', 'name', 'email', 'role'),
            'tenant' => $tenant->only('id', 'name', 'slug'),
            'expires_in' => 7200,
        ]);
    }
}
