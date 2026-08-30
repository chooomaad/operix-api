<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentEmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche d'employés réservée au rôle AGENT.
 *
 * Un agent doit pouvoir retrouver un employé de SON entreprise par matricule ou par
 * nom, et n'obtenir que son identité professionnelle minimale (matricule, nom,
 * statut). Il n'a PAS accès au module Employees complet : cet endpoint dédié est sa
 * seule porte, gardée par la permission employees.agent_search.
 *
 * SÉCURITÉ (côté serveur, jamais le frontend) :
 *  - authentification + tenant actif : garantis par auth:sanctum + EnsureTenantContext ;
 *  - autorisation : permission employees.agent_search (agents uniquement) ;
 *  - cloisonnement tenant : Employee porte le global scope BelongsToTenant, la
 *    requête est donc AUTOMATIQUEMENT restreinte à l'entreprise de l'agent — un
 *    employé d'un autre tenant est hors de portée, quel que soit le terme cherché ;
 *  - projection minimale : AgentEmployeeResource n'expose que 3 champs.
 */
class AgentEmployeeController extends Controller
{
    private const LIMIT = 20;

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $like = '%' . $validated['q'] . '%';

        // Recherche par matricule OU nom OU prénom OU nom complet. Bindings
        // paramétrés, colonnes statiques : aucune injection possible. Le global
        // scope tenant s'applique — pas de filtrage tenant manuel à oublier.
        $employees = Employee::query()
            ->where(function ($w) use ($like) {
                $w->whereRaw('matricule ILIKE ?', [$like])
                  ->orWhereRaw('nom ILIKE ?', [$like])
                  ->orWhereRaw('prenom ILIKE ?', [$like])
                  ->orWhereRaw("(prenom || ' ' || nom) ILIKE ?", [$like]);
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit(self::LIMIT)
            ->get(['id', 'matricule', 'nom', 'prenom', 'is_active', 'tenant_id']);

        return response()->json([
            'data' => AgentEmployeeResource::collection($employees),
        ]);
    }
}
