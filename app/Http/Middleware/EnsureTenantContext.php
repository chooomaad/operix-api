<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->tenant_id === null) {
            return response()->json(['message' => 'Votre compte n\'est rattache a aucune entreprise.'], 403);
        }

        // Un compte désactivé ne doit plus accéder à l'API, même muni d'un jeton
        // encore valide : la connexion refuse déjà les comptes inactifs, mais un
        // jeton émis avant la désactivation survivrait sans ce garde. Aligne l'API
        // sur la règle déjà appliquée aux canaux temps réel (RealtimeChannelAccess).
        if (! $user->is_active) {
            return response()->json(['message' => 'Votre compte a ete desactive.'], 403);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (! $tenant) {
            return response()->json(['message' => 'Votre entreprise est introuvable.'], 403);
        }

        if ($tenant->isSuspended()) {
            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez support@operix-app.com',
            ], 403);
        }

        if (! $tenant->allowsApplicationAccess()) {
            return response()->json(['message' => 'Votre acces a cette entreprise a expire.'], 403);
        }

        return $next($request);
    }
}
