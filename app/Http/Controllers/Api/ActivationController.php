<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Activation du compte du premier company_admin.
 *
 * L'utilisateur définit LUI-MÊME son mot de passe via un token d'activation (jamais de
 * mot de passe en clair envoyé). Token usage unique + expiration courte (ActivationService).
 */
class ActivationController extends Controller
{
    public function activate(Request $request, ActivationService $activations): JsonResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $activation = $activations->resolve($validated['token']);

        if (! $activation) {
            return response()->json(['message' => 'Lien d’activation invalide ou expiré.'], 422);
        }

        $user = $activation->user;
        $user->update([
            'password'  => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $activations->consume($activation);

        return response()->json(['message' => 'Compte activé. Vous pouvez maintenant vous connecter.']);
    }
}
