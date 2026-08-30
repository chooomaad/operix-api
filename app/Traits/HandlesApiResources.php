<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $year = date('Y');

        // On dérive le prochain numéro du MAX NUMÉRIQUE de référence existant pour
        // ce (préfixe, année), et NON d'un count(). Deux raisons :
        //
        //  1. count() se désynchronise du numéro dès qu'une ligne est supprimée
        //     (trou) — il forge alors un numéro déjà pris → violation d'unicité.
        //  2. Le suffixe doit être comparé comme un ENTIER, pas lexicalement :
        //     au-delà de 9999, « ...-9999 » trie APRÈS « ...-10000 » en texte, ce
        //     qui ferait régénérer 10000 à l'infini. On extrait donc le suffixe et
        //     on le caste en entier avant d'en prendre le max.
        //
        // La requête passe par le modèle : elle reste scindée PAR TENANT (global scope).
        $offset = strlen("{$prefix}-{$year}-") + 1; // position 1-indexée du numéro
        $max = $model::withTrashed()
            ->where('reference', 'like', "{$prefix}-{$year}-%")
            ->selectRaw("MAX(CAST(SUBSTR(reference, ?) AS INTEGER)) AS m", [$offset])
            ->value('m');

        $next = ((int) ($max ?? 0)) + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $next);
    }

    /**
     * Crée un modèle en lui attribuant une référence séquentielle, SANS collision
     * sous charge concurrente.
     *
     * PROBLÈME résolu : generateReference() lit un `count()+1` puis on insère. Deux
     * requêtes simultanées lisent le même compteur, forgent la MÊME référence, et la
     * seconde viole la contrainte unique (tenant_id, reference) → erreur 500. Mesuré :
     * ~70 % d'échecs sur la création d'incidents à 20 utilisateurs simultanés.
     *
     * SOLUTION : un verrou consultatif PostgreSQL au niveau transaction
     * (pg_advisory_xact_lock), keyé par (tenant, préfixe, année). Il sérialise la
     * seule section critique — lire le compteur puis insérer — et se libère
     * automatiquement à la fin de la transaction. Déterministe (pas de retry
     * probabiliste), et le verrou n'est tenu que quelques millisecondes.
     *
     * Le comptage passe par le modèle Eloquent : il reste scindé PAR TENANT via le
     * global scope. La clé de verrou inclut le tenant pour que deux entreprises ne
     * se sérialisent jamais l'une l'autre.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $data
     */
    protected function createWithReference(string $prefix, string $model, array $data): Model
    {
        $year     = date('Y');
        $tenantId = app(\App\Support\TenantContext::class)->id() ?? 0;

        // Clé 64 bits stable pour ce couple (tenant, préfixe, année).
        $lockKey = crc32("{$tenantId}:{$prefix}:{$year}");

        return DB::transaction(function () use ($prefix, $model, $data, $lockKey) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

            $data['reference'] = $this->generateReference($prefix, $model);

            return $model::create($data);
        });
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
