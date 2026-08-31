<?php

namespace App\Http\Requests\Concerns;

use App\Support\TenantContext;
use Illuminate\Validation\Rule;

/**
 * Règles de validation communes aux 4 modules HSSE (Incident, Near Miss,
 * Breach of Process, Environment) pour le champ « personnes impliquées ».
 *
 * Logique UNIQUE et partagée : chaque identifiant employé doit exister DANS le
 * tenant courant. Un événement ne peut donc jamais référencer un employé d'un
 * autre tenant (isolation multi-tenant appliquée dès la validation, avant même
 * l'écriture en base). Les employés supprimés (soft delete) sont également exclus.
 */
trait ValidatesInvolvedEmployees
{
    protected function involvedEmployeesRules(): array
    {
        return [
            'employees'   => ['nullable', 'array'],
            'employees.*' => ['integer', $this->tenantEmployeeExists()],
        ];
    }

    /**
     * Règle `exists` scopée au tenant courant, réutilisable pour un champ
     * employé unique (ex. le `employee_id` legacy du module Breach).
     */
    protected function tenantEmployeeExists(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('employees', 'id')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->whereNull('deleted_at');
    }
}
