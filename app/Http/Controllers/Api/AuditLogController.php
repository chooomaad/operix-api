<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('user:id,name,email');

        if ($request->filled('action'))     $query->where('action',     $request->action);
        if ($request->filled('model_type')) $query->where('model_type', $request->model_type);
        if ($request->filled('user_id'))    $query->where('user_id',    $request->user_id);
        if ($request->filled('from'))       $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))         $query->whereDate('created_at', '<=', $request->to);

        // Filtre par VERBE (created / updated / deleted…) : les actions sont
        // nommees « {modele}_{verbe} », on filtre donc sur le suffixe.
        if ($request->filled('verb')) {
            $query->where('action', 'like', '%_' . $request->string('verb'));
        }

        // Recherche libre : action, type de modele, ou nom de l'utilisateur.
        // Parametree (bindings), insensible a la casse.
        if ($request->filled('search')) {
            $term = '%' . strtolower(trim($request->string('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(action) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(model_type) LIKE ?', [$term])
                  ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }

        $perPage   = min($request->integer('per_page', 50), 200);
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

    public function show(int $id): JsonResponse
    {
        $log = ActivityLog::with('user:id,name,email')->findOrFail($id);
        return response()->json($log);
    }
}
