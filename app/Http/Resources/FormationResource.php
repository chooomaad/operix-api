<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'employee_id'  => $this->employee_id,
            'employee'     => $this->whenLoaded('employee', fn () => [
                'id'        => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'matricule' => $this->employee->matricule,
            ]),
            'titre'        => $this->titre,
            'organisme'    => $this->organisme,
            'type'         => $this->type,
            'date_debut'   => $this->date_debut?->format('Y-m-d'),
            'date_fin'     => $this->date_fin?->format('Y-m-d'),
            'duree_jours'  => $this->duree_jours,
            'statut'       => $this->statut,
            'certificat'   => $this->certificat,
            'observations' => $this->observations,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
