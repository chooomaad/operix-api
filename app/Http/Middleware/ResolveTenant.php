<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout le tenant de la requête EXCLUSIVEMENT depuis l'utilisateur authentifié.
 *
 * Ne lit jamais de tenant_id/slug envoyé par le client (header, query, corps).
 * À appliquer après `auth:sanctum` sur les routes métier.
 *
 * NB : la vérification du statut (tenant suspendu) et le rejet des utilisateurs
 * sans tenant sont ajoutés par EnsureTenantContext (commit 9).
 */
class ResolveTenant
{
    public function __construct(private TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id !== null) {
            $this->context->set((int) $user->tenant_id);
        }

        return $next($request);
    }
}
