<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
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
            'reference'             => $this->reference,
            'date'                  => $this->date?->format('Y-m-d'),
            'time'                  => $this->time,
            'location'              => $this->location,
            'type'                  => $this->type,
            'severity'              => $this->severity,
            'description'           => $this->description,
            'immediate_cause'       => $this->immediate_cause,
            'root_cause'            => $this->root_cause,
            'corrective_action'     => $this->corrective_action,
            'corrective_action_due' => $this->corrective_action_due?->format('Y-m-d'),
            'status'                => $this->status,
            'employees'             => $this->employees,
            'image'                 => $this->image,
            'image_url'             => app(\App\Services\TenantFileService::class)->url($this->image),
            'reported_by'           => $this->whenLoaded('reporter', fn() => [
                'id'   => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'created_at'            => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
