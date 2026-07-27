<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope appliquant l'isolation tenant à toute lecture Eloquent d'un modèle
 * tenant-scoped (voir le trait BelongsToTenant).
 *
 * Comportement :
 *  - bypass explicite (super admin / plateforme) → aucun filtre ;
 *  - contexte tenant présent → filtre `tenant_id = <contexte>` ;
 *  - aucun contexte, en HTTP → fail-closed (`1 = 0`) : rien n'est exposé (défense en
 *    profondeur, ex. route mal configurée, super_admin sans tenant sur une route métier) ;
 *  - aucun contexte, en console (seeders, commandes, jobs sync, création de tenant) →
 *    aucun filtre (contextes de confiance).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->bypassed()) {
            return;
        }

        $tenantId = $context->id();

        if ($tenantId !== null) {
            $builder->where($model->getQualifiedTenantColumn(), $tenantId);
            return;
        }

        if (! app()->runningInConsole()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
