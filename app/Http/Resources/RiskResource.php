<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'reference'           => $this->reference,
            'date_identification' => $this->date_identification?->format('Y-m-d'),
            'location'            => $this->location,
            'department_id'       => $this->department_id,
            'department'          => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ]),
            'activity'            => $this->activity,
            'category'            => $this->category,
            'assessment_type'     => $this->assessment_type,
            'danger'              => $this->danger,
            'risk_description'    => $this->risk_description,
            'causes'              => $this->causes,
            'consequences'        => $this->consequences,
            'exposed_persons'     => $this->exposed_persons,
            'existing_controls'   => $this->existing_controls,
            'owner_id'            => $this->owner_id,
            'owner'               => $this->whenLoaded('owner', fn () => [
                'id'   => $this->owner->id,
                'name' => $this->owner->name,
            ]),

            // Évaluation initiale — score et niveau calculés côté serveur.
            'probability'         => $this->probability,
            'severity'            => $this->severity,
            'score'               => $this->score,
            'level'               => $this->level,

            'controls'            => $this->controls ?? [],

            // Évaluation résiduelle (après mesures de contrôle).
            'residual_probability' => $this->residual_probability,
            'residual_severity'    => $this->residual_severity,
            'residual_score'       => $this->residual_score,
            'residual_level'       => $this->residual_level,

            'status'      => $this->status,
            'review_date' => $this->review_date?->format('Y-m-d'),

            'actions_count'  => $this->whenCounted('actions'),
            'created_at'     => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'     => $this->updated_at?->format('Y-m-d H:i'),
            'actions'        => RiskActionResource::collection($this->whenLoaded('actions')),
        ];
    }
}
