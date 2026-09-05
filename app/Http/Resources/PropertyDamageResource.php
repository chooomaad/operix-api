<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyDamageResource extends JsonResource
{
    /**
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
            'estimated_cost'        => $this->estimated_cost !== null ? (float) $this->estimated_cost : null,
            'immediate_cause'       => $this->immediate_cause,
            'corrective_action'     => $this->corrective_action,
            'corrective_action_due' => $this->corrective_action_due?->format('Y-m-d'),
            'status'                => $this->status,
            'involved_people'       => \App\Support\People::resolve($this->involved_people ?? []),
            'image'                 => $this->image,
            'image_url'             => app(\App\Services\TenantFileService::class)->url($this->image),
            'report_file'           => $this->report_file,
            'report_file_url'       => app(\App\Services\TenantFileService::class)->url($this->report_file),
            'reported_by'           => $this->whenLoaded('reporter', fn() => [
                'id'   => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'location_point'        => $this->latitude === null ? null : [
                'latitude'    => $this->latitude,
                'longitude'   => $this->longitude,
                'accuracy'    => $this->location_accuracy,
                'captured_at' => $this->location_captured_at?->toIso8601String(),
            ],
            'created_at'            => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
