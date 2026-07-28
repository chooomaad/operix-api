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
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'matricule'             => $this->matricule,
            'nom'                   => $this->nom,
            'prenom'                => $this->prenom,
            'full_name'             => $this->full_name,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'poste'                 => $this->poste,
            'type_contrat'          => $this->type_contrat,
            'date_embauche'         => $this->date_embauche?->format('Y-m-d'),
            'date_fin_contrat'      => $this->date_fin_contrat?->format('Y-m-d'),
            'gender'                => $this->gender,
            'date_naissance'        => $this->date_naissance?->format('Y-m-d'),
            'nationalite'           => $this->nationalite,
            'lieu_naissance'        => $this->lieu_naissance,
            'adresse'               => $this->adresse,
            'num_cni'               => $this->num_cni,
            'contact_urgence_nom'   => $this->contact_urgence_nom,
            'contact_urgence_tel'   => $this->contact_urgence_tel,
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
