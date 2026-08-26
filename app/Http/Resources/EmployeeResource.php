<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    /**
     * Les données personnelles (identité civile, adresse, contacts) ne sont exposées
     * qu'aux détenteurs de `employees.pii.view`.
     *
     * L'annuaire du personnel est ouvert à tous les rôles : sans filtrage, n'importe
     * quel agent lisait le numéro de pièce d'identité et l'adresse de ses collègues.
     * Le risque est nettement plus élevé depuis un mobile — appareil personnel, perdu
     * ou partagé — que depuis un poste de bureau (docs/MOBILE_API_READINESS.md §B8).
     *
     * Les champs professionnels (nom, matricule, poste, département, photo) restent
     * visibles de tous : ils sont nécessaires pour rattacher un employé à un incident.
     */
    public function toArray(Request $request): array
    {
        $canViewPii = $request->user()?->can('employees.pii.view') ?? false;

        return [
            'id'                    => $this->id,
            'matricule'             => $this->matricule,
            'nom'                   => $this->nom,
            'prenom'                => $this->prenom,
            'full_name'             => $this->full_name,
            'email'                 => $this->when($canViewPii, fn () => $this->email),
            'phone'                 => $this->when($canViewPii, fn () => $this->phone),
            'poste'                 => $this->poste,
            'type_contrat'          => $this->type_contrat,
            'date_embauche'         => $this->date_embauche?->format('Y-m-d'),
            'date_fin_contrat'      => $this->date_fin_contrat?->format('Y-m-d'),
            'gender'                => $this->gender,
            'date_naissance'        => $this->when($canViewPii, fn () => $this->date_naissance?->format('Y-m-d')),
            'nationalite'           => $this->when($canViewPii, fn () => $this->nationalite),
            'lieu_naissance'        => $this->when($canViewPii, fn () => $this->lieu_naissance),
            'adresse'               => $this->when($canViewPii, fn () => $this->adresse),
            'num_cni'               => $this->when($canViewPii, fn () => $this->num_cni),
            'contact_urgence_nom'   => $this->when($canViewPii, fn () => $this->contact_urgence_nom),
            'contact_urgence_tel'   => $this->when($canViewPii, fn () => $this->contact_urgence_tel),
            'is_active'             => $this->is_active,
            'photo'                 => $this->photo,
            'photo_url'             => app(\App\Services\TenantFileService::class)->url($this->photo),
            'department'            => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ]),
            'formations_count'      => $this->whenCounted('formations'),
            'certifications_count'  => $this->whenCounted('certifications'),
            'incidents_count'       => $this->whenCounted('safetyIncidents'),
            'created_at'            => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
