<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'employee_id'     => $this->employee_id,
            'employee'        => $this->whenLoaded('employee', fn () => [
                'id'        => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'matricule' => $this->employee->matricule,
            ]),
            'titre'           => $this->titre,
            'organisme'       => $this->organisme,
            'numero'          => $this->numero,
            'date_obtention'  => $this->date_obtention?->format('Y-m-d'),
            'date_expiration' => $this->date_expiration?->format('Y-m-d'),
            'document'        => $this->document,
            'is_expired'      => (bool) $this->is_expired,
            'expires_in_days' => $this->date_expiration
                                    ? (int) now()->diffInDays($this->date_expiration, false)
                                    : null,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
