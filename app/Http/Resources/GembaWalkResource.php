<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GembaWalkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'date'            => $this->date?->format('Y-m-d'),
            'zone'            => $this->zone,
            'objective'       => $this->objective,
            'auditor'         => $this->auditor,
            'observation'     => $this->observation,
            'action_required' => $this->action_required,
            'responsible'     => $this->responsible,
            'due_date'        => $this->due_date?->format('Y-m-d'),
            'priority'        => $this->priority,
            'status'          => $this->status,
            'image'           => $this->image,
            'image_url'       => app(\App\Services\TenantFileService::class)->url($this->image),
            'is_overdue'      => $this->due_date && $this->due_date->isPast() && $this->status !== 'resolved',
            'created_by'      => $this->whenLoaded('creator', fn() => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
