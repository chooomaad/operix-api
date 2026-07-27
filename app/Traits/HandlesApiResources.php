<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Helpers partagés par les contrôleurs HSSE : pagination, génération de références
 * et journal d'audit.
 *
 * IMPORTANT : ce trait ne réalise AUCUN scoping tenant. L'isolation multi-tenant est
 * assurée par le global scope App\Models\Scopes\TenantScope (via le trait
 * App\Models\Concerns\BelongsToTenant appliqué aux modèles). generateReference() compte
 * via le modèle Eloquent : le comptage est donc déjà scindé PAR TENANT grâce au global scope.
 *
 * (Anciennement nommé HasTenantScope — nom trompeur, renommé pour lever l'ambiguïté.)
 */
trait HandlesApiResources
{
    protected function paginateQuery(Builder $query, Request $request, int $default = 25): array
    {
        $perPage   = min($request->integer('per_page', $default), 100);
        $paginated = $query->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ];
    }

    protected function generateReference(string $prefix, string $model): string
    {
        $year  = date('Y');
        $count = $model::whereYear('created_at', $year)->withTrashed()->count() + 1;
        return sprintf('%s-%s-%04d', $prefix, $year, $count);
    }

    protected function auditLog(
        Request $request,
        string  $action,
        string  $modelType,
        int     $modelId,
        ?array  $oldValues = null,
        ?array  $newValues = null
    ): void {
        ActivityLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
