<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = $this->tenantUserQuery($request)
            ->when($request->filled('search'), fn ($q) =>
                $q->where(fn ($s) =>
                    $s->where('name', 'ilike', "%{$request->search}%")
                      ->orWhere('email', 'ilike', "%{$request->search}%")
                      ->orWhere('matricule', 'ilike', "%{$request->search}%")
                )
            )
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('is_active'), fn ($q) =>
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $users->map(fn ($u) => $this->formatUser($u)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', 'unique:users,email'],
            'role'      => ['required', Rule::in(['company_admin', 'hsse_manager', 'supervisor', 'agent'])],
            'matricule' => ['nullable', 'string', 'unique:users,matricule'],
            'phone'     => 'nullable|string|max:20',
            'password'  => 'required|string|min:4|max:50',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'matricule' => $validated['matricule'] ?? null,
            'phone'     => $validated['phone'] ?? null,
            'password'  => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($this->formatUser($user), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->formatUser($this->tenantUserQuery($request)->findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->tenantUserQuery($request)->findOrFail($id);

        if ($user->id === $request->user()->id && $request->filled('role') && $request->role !== $user->role) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre rôle.'], 422);
        }

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
            'role'      => ['sometimes', Rule::in(['company_admin', 'hsse_manager', 'supervisor', 'agent'])],
            'matricule' => ['nullable', 'string', Rule::unique('users')->ignore($id)],
            'phone'     => 'nullable|string|max:20',
            'password'  => 'sometimes|nullable|string|min:4|max:50',
            'is_active' => 'boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($this->formatUser($user->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->tenantUserQuery($request)->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        // Garde-fou anti-verrouillage : ne jamais supprimer le DERNIER administrateur
        // d'entreprise, sinon plus personne ne pourrait gérer les comptes ni les rôles.
        if ($user->role === 'company_admin') {
            $otherAdmins = $this->tenantUserQuery($request)
                ->where('role', 'company_admin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherAdmins === 0) {
                return response()->json([
                    'message' => "Impossible de supprimer le dernier administrateur de l'entreprise.",
                ], 422);
            }
        }

        // Suppression définitive : le compte et ses jetons sont retirés. Les données
        // métier référençant l'utilisateur (incidents signalés, journal d'audit) sont
        // conservées, leur auteur passant simplement à « null » (FK nullOnDelete).
        // Pour préserver l'attribution, préférer la désactivation.
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    private function formatUser(User $u): array
    {
        return [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'role'       => $u->role,
            'matricule'  => $u->matricule,
            'phone'      => $u->phone,
            'avatar'     => $u->avatar,
            'is_active'  => $u->is_active,
            // Derniere connexion reelle, posee par AuthController a chaque login.
            // Null tant que le compte ne s'est jamais connecte (affiche « — »).
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'last_seen_at'  => $u->last_seen_at?->toIso8601String(),
            // « En ligne » = activite dans les 2 dernieres minutes (heartbeat pose
            // par le middleware TrackPresence a chaque requete API authentifiee).
            'is_online'     => $u->last_seen_at !== null && $u->last_seen_at->gt(now()->subMinutes(2)),
            'created_at' => $u->created_at?->toDateString(),
        ];
    }

    private function tenantUserQuery(Request $request): Builder
    {
        return User::query()->where('tenant_id', $request->user()->tenant_id);
    }
}
