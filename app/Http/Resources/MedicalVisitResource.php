<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'employee_id'      => $this->employee_id,
            'employee'         => $this->whenLoaded('employee', fn () => [
                'id'        => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'matricule' => $this->employee->matricule,
            ]),
            'type'             => $this->type,
            'date'             => $this->date?->format('Y-m-d'),
            'prochaine_visite' => $this->prochaine_visite?->format('Y-m-d'),
            'medecin'          => $this->medecin,
            'resultat'         => $this->resultat,
            'restrictions'     => $this->restrictions,
            'document'         => $this->document,
            'observations'     => $this->observations,
            'is_expired'       => $this->prochaine_visite && $this->prochaine_visite->isPast(),
            'expires_in_days'  => $this->prochaine_visite
                                    ? (int) now()->diffInDays($this->prochaine_visite, false)
                                    : null,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
