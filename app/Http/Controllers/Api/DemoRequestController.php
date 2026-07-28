<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Soumission PUBLIQUE d'une demande de démo depuis le site marketing.
 * Rate limiting appliqué au niveau de la route (anti-spam).
 */
class DemoRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name'   => ['required', 'string', 'max:255'],
            'contact_name'   => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'message'        => ['nullable', 'string', 'max:2000'],
        ]);

        $demo = DemoRequest::create($validated + [
            'status'     => 'new',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message'   => 'Votre demande de démo a bien été enregistrée. Notre équipe vous contactera.',
            'reference' => $demo->reference,
        ], 201);
    }
}
