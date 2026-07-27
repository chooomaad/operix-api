<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * À appliquer sur tout modèle appartenant à une entreprise (tenant).
 *
 * - Ajoute le global scope d'isolation (TenantScope).
 * - Auto-affecte tenant_id à la création DEPUIS le contexte serveur, uniquement si
 *   la valeur est absente. tenant_id n'étant pas `fillable`, un client ne peut pas le
 *   fournir par mass-assignment ; les factories (exécutées en Model::unguarded) peuvent
 *   en revanche le fixer explicitement, ce qui est préservé.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            $column = $model->getTenantColumn();

            if ($model->getAttribute($column) === null) {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId !== null) {
                    $model->setAttribute($column, $tenantId);
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable() . '.' . $this->getTenantColumn();
    }
}
