<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Utilitaires partagés par tous les contrôleurs HSSE.
 * Pagination, génération de références et journal d'audit.
 */
trait HasTenantScope
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
