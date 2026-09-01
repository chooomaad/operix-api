<?php

namespace App\Http\Requests\Concerns;

/**
 * Compatibilité ascendante : l'ancien frontend envoie « employees » = [id, …]
 * (uniquement des employés). Le nouveau format est « involved_people » =
 * [{type,id}]. Tant que le picker multi-type n'est pas déployé, on convertit
 * automatiquement l'ancien format vers le nouveau afin de ne rien casser.
 */
trait ConvertsLegacyInvolved
{
    protected function prepareForValidation(): void
    {
        if ($this->has('employees') && ! $this->has('involved_people')) {
            $people = collect($this->input('employees', []))
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => ['type' => 'employee', 'id' => (int) $id])
                ->values()->all();
            $this->merge(['involved_people' => $people]);
        }
    }
}
