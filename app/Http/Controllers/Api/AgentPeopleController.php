<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche « personnes » réservée au rôle AGENT (employee / contractor /
 * visitor / intern). Même porte que la recherche employé agent : permission
 * employees.agent_search. Le cloisonnement tenant est garanti par les global
 * scopes des modèles (via App\Support\People) — un agent ne voit que les
 * personnes de son entreprise.
 */
class AgentPeopleController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'    => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'in:employee,contractor,visitor,intern'],
        ]);

        $results = People::search($validated['q'], $validated['type'] ?? null, 20);

        return response()->json(['data' => $results]);
    }
}
