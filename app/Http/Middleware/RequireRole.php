<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        // super_admin bypasses all role checks
        if (!$user || ($user->role !== 'super_admin' && !in_array($user->role, $roles))) {
            return response()->json(['message' => 'Accès refusé. Rôle insuffisant.'], 403);
        }

        return $next($request);
    }
}
