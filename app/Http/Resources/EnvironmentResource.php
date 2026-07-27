<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnvironmentResource extends JsonResource
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
            'location'              => $this->location,
            'type'                  => $this->type,
            'severity'              => $this->severity,
            'description'           => $this->description,
            'impact'                => $this->impact,
            'corrective_action'     => $this->corrective_action,
            'corrective_action_due' => $this->corrective_action_due?->format('Y-m-d'),
            'status'                => $this->status,
            'image'                 => $this->image,
            'reported_by'           => $this->whenLoaded('reporter', fn() => [
                'id'   => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'created_at'            => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
