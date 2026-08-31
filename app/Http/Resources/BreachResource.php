<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreachResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'reference'   => $this->reference,
            'date'        => $this->date?->format('Y-m-d'),
            'type'        => $this->type,
            'severity'    => $this->severity,
            'employees'   => $this->employees ?? [],
            'description' => $this->description,
            'sanction'    => $this->sanction,
            'employee'    => $this->whenLoaded('employee', fn() => [
                'id'        => $this->employee->id,
                'matricule' => $this->employee->matricule,
                'full_name' => $this->employee->full_name,
                'poste'     => $this->employee->poste,
            ]),
            'created_by'  => $this->whenLoaded('creator', fn() => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at'  => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
