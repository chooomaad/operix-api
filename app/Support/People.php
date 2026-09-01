<?php

namespace App\Support;

use App\Models\ContractorEmployee;
use App\Models\Employee;
use App\Models\Intern;
use App\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * Abstraction centrale « Personne » impliquable dans un événement HSSE.
 *
 * Une personne est TOUJOURS identifiée par (type + id), jamais par son nom :
 *   - employee   → App\Models\Employee            (matricule)
 *   - contractor → App\Models\ContractorEmployee  (badge / CE-id) + entreprise
 *   - visitor    → App\Models\Visitor             (badge / VIS-id)
 *   - intern     → App\Models\Intern              (référence INT-…)
 *
 * Toutes les requêtes passent par les modèles Eloquent → le global scope tenant
 * garantit l'isolation multi-tenant (impossible de voir/associer une personne
 * d'un autre tenant).
 */
class People
{
    public const TYPES = ['employee', 'contractor', 'visitor', 'intern'];

    /** @return class-string|null */
    public static function modelFor(string $type): ?string
    {
        return [
            'employee'   => Employee::class,
            'contractor' => ContractorEmployee::class,
            'visitor'    => Visitor::class,
            'intern'     => Intern::class,
        ][$type] ?? null;
    }

    /**
     * La personne (type,id) existe-t-elle DANS le tenant courant ?
     * (le global scope tenant s'applique → pas de fuite inter-tenant).
     */
    public static function exists(string $type, int $id): bool
    {
        $model = self::modelFor($type);
        return $model !== null && $model::whereKey($id)->exists();
    }

    /**
     * Recherche serveur, multi-type, tenant-scopée. Retourne des personnes
     * normalisées : {type,id,first_name,last_name,full_name,identifier,company}.
     *
     * @param  string       $q      terme (nom, prénom, matricule, référence, entreprise)
     * @param  string|null  $type   restreindre à un type (sinon tous)
     * @param  int          $limit  résultats max par type
     */
    public static function search(string $q, ?string $type = null, int $limit = 10): Collection
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }
        $types = $type && in_array($type, self::TYPES, true) ? [$type] : self::TYPES;
        $like  = '%' . $q . '%';

        $out = collect();

        if (in_array('employee', $types, true)) {
            Employee::query()
                ->where(fn ($w) => $w->where('nom', 'ilike', $like)
                    ->orWhere('prenom', 'ilike', $like)
                    ->orWhere('matricule', 'ilike', $like)
                    ->orWhere('poste', 'ilike', $like))
                ->orderBy('nom')->limit($limit)->get()
                ->each(fn ($e) => $out->push(self::normalize('employee', $e)));
        }

        if (in_array('contractor', $types, true)) {
            ContractorEmployee::query()->with('contractor:id,company_name')
                ->where(fn ($w) => $w->where('nom', 'ilike', $like)
                    ->orWhere('prenom', 'ilike', $like)
                    ->orWhere('badge_number', 'ilike', $like)
                    ->orWhereHas('contractor', fn ($c) => $c->where('company_name', 'ilike', $like)))
                ->orderBy('nom')->limit($limit)->get()
                ->each(fn ($c) => $out->push(self::normalize('contractor', $c)));
        }

        if (in_array('visitor', $types, true)) {
            Visitor::query()
                ->where(fn ($w) => $w->where('nom', 'ilike', $like)
                    ->orWhere('prenom', 'ilike', $like)
                    ->orWhere('badge_number', 'ilike', $like)
                    ->orWhere('entreprise', 'ilike', $like))
                ->orderBy('nom')->limit($limit)->get()
                ->each(fn ($v) => $out->push(self::normalize('visitor', $v)));
        }

        if (in_array('intern', $types, true)) {
            Intern::query()
                ->where(fn ($w) => $w->where('nom', 'ilike', $like)
                    ->orWhere('prenom', 'ilike', $like)
                    ->orWhere('reference', 'ilike', $like)
                    ->orWhere('etablissement', 'ilike', $like))
                ->orderBy('nom')->limit($limit)->get()
                ->each(fn ($i) => $out->push(self::normalize('intern', $i)));
        }

        return $out->values();
    }

    /**
     * Résout une liste [{type,id}] en personnes normalisées (pour l'affichage
     * des chips / historique). Ignore silencieusement les entrées introuvables.
     *
     * @param  array<int,array{type:string,id:int}>  $refs
     */
    public static function resolve(array $refs): Collection
    {
        $byType = collect($refs)
            ->filter(fn ($r) => isset($r['type'], $r['id']) && in_array($r['type'], self::TYPES, true))
            ->groupBy('type');

        $out = collect();
        foreach ($byType as $type => $items) {
            $ids   = collect($items)->pluck('id')->map(fn ($i) => (int) $i)->all();
            $model = self::modelFor($type);
            $query = $model::query()->whereIn('id', $ids);
            if ($type === 'contractor') {
                $query->with('contractor:id,company_name');
            }
            $query->get()->each(fn ($m) => $out->push(self::normalize($type, $m)));
        }
        return $out->values();
    }

    /** Normalise un modèle vers la forme « personne ». */
    private static function normalize(string $type, $m): array
    {
        return match ($type) {
            'employee' => [
                'type' => 'employee', 'id' => $m->id,
                'first_name' => $m->prenom, 'last_name' => $m->nom,
                'full_name'  => trim("{$m->prenom} {$m->nom}"),
                'identifier' => $m->matricule,
                'company'    => $m->entreprise,
            ],
            'contractor' => [
                'type' => 'contractor', 'id' => $m->id,
                'first_name' => $m->prenom, 'last_name' => $m->nom,
                'full_name'  => trim("{$m->prenom} {$m->nom}"),
                'identifier' => $m->badge_number ?: "CE-{$m->id}",
                'company'    => $m->contractor?->company_name,
            ],
            'visitor' => [
                'type' => 'visitor', 'id' => $m->id,
                'first_name' => $m->prenom, 'last_name' => $m->nom,
                'full_name'  => trim("{$m->prenom} {$m->nom}"),
                'identifier' => $m->badge_number ?: "VIS-{$m->id}",
                'company'    => $m->entreprise,
            ],
            'intern' => [
                'type' => 'intern', 'id' => $m->id,
                'first_name' => $m->prenom, 'last_name' => $m->nom,
                'full_name'  => trim("{$m->prenom} {$m->nom}"),
                'identifier' => $m->reference ?: "INT-{$m->id}",
                'company'    => $m->etablissement,
            ],
        };
    }
}
