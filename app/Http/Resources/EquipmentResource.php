<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'code'                      => $this->code,
            'name'                      => $this->name,
            'category'                  => $this->category,
            'brand'                     => $this->brand,
            'model'                     => $this->model,
            'serial_number'             => $this->serial_number,
            'purchase_date'             => $this->purchase_date?->format('Y-m-d'),
            'last_inspection'           => $this->last_inspection?->format('Y-m-d'),
            'next_inspection'           => $this->next_inspection?->format('Y-m-d'),
            'inspection_frequency_days' => $this->inspection_frequency_days,
            'inspection_due'            => $this->isInspectionDue(),
            'status'                    => $this->status,
            'location'                  => $this->location,
            'photo'                     => $this->photo,
            'photo_url'                 => app(\App\Services\TenantFileService::class)->url($this->photo),
            'notes'                     => $this->notes,
            'assigned_employee'         => $this->whenLoaded('assignedEmployee', fn() => [
                'id'        => $this->assignedEmployee->id,
                'full_name' => $this->assignedEmployee->full_name,
                'matricule' => $this->assignedEmployee->matricule,
            ]),
            'created_at'                => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
