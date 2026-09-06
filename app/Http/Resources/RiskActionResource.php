<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiskActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'risk_id'        => $this->risk_id,
            'description'    => $this->description,
            'type'           => $this->type,
            'responsible_id' => $this->responsible_id,
            'responsible'    => $this->whenLoaded('responsible', fn () => [
                'id'   => $this->responsible->id,
                'name' => $this->responsible->name,
            ]),
            'due_date'    => $this->due_date?->format('Y-m-d'),
            'priority'    => $this->priority,
            'status'      => $this->status,
            'is_overdue'  => $this->is_overdue,
            'budget'      => $this->budget !== null ? (float) $this->budget : null,
            'proof'       => $this->proof,
            'proof_url'   => $this->proof_url,
            'closed_at'   => $this->closed_at?->format('Y-m-d'),
            'validated_by' => $this->validated_by,
            'validator'   => $this->whenLoaded('validator', fn () => [
                'id'   => $this->validator->id,
                'name' => $this->validator->name,
            ]),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'created_at'   => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
