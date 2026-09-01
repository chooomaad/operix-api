<?php

namespace App\Http\Requests\Concerns;

use App\Support\People;
use Illuminate\Validation\Rule;

/**
 * Règles communes aux 4 modules HSSE pour le champ « personnes impliquées »
 * (Incident, Near Miss, Breach of Process, Environment).
 *
 * Format attendu : involved_people = [ { "type": "...", "id": N }, … ]
 * où type ∈ {employee, contractor, visitor, intern}.
 *
 * L'existence de CHAQUE personne est vérifiée DANS le tenant courant (via
 * App\Support\People::exists, qui interroge les modèles → global scope tenant).
 * Une personne d'un autre tenant, ou un couple type/id inexistant, échoue.
 */
trait ValidatesInvolvedPeople
{
    protected function involvedPeopleRules(): array
    {
        return [
            'involved_people.*'        => ['array'],
            'involved_people.*.type'   => ['required', Rule::in(People::TYPES)],
            'involved_people.*.id'     => ['required', 'integer'],
            // Existence tenant-scopée de chaque personne, vérifiée en un point.
            'involved_people'          => ['nullable', 'array', function ($attr, $value, $fail) {
                foreach ((array) $value as $p) {
                    if (! is_array($p) || ! isset($p['type'], $p['id']) || ! People::exists((string) $p['type'], (int) $p['id'])) {
                        $ref = is_array($p) ? (($p['type'] ?? '?') . '#' . ($p['id'] ?? '?')) : '?';
                        $fail("La personne impliquée « {$ref} » est introuvable dans votre organisation.");
                    }
                }
            }],
        ];
    }
}
