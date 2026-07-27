<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermitToWorkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'type'           => $this->type,
            'title'          => $this->title,
            'description'    => $this->description,
            'location'       => $this->location,
            'contractor'     => $this->whenLoaded('contractor', fn () => [
                'id'   => $this->contractor->id,
                'name' => $this->contractor->name,
            ]),
            'contractor_name'=> $this->contractor_name,
            'valid_from'     => $this->valid_from?->toIso8601String(),
            'valid_to'       => $this->valid_to?->toIso8601String(),
            'status'         => $this->status,
            'risks'          => $this->risks ?? [],
            'precautions'    => $this->precautions ?? [],
            'ppe_required'   => $this->ppe_required ?? [],
            'workers'        => $this->workers ?? [],
            'requested_by'   => $this->whenLoaded('requestedBy', fn () => [
                'id'   => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
            ]),
            'approved_by'    => $this->whenLoaded('approvedBy', fn () => [
                'id'   => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),
            'approved_at'    => $this->approved_at?->toIso8601String(),
            'approval_notes' => $this->approval_notes,
            'closure_notes'  => $this->closure_notes,
            'closed_at'      => $this->closed_at?->toIso8601String(),
            'is_active'      => $this->isActive(),
            'is_expired'     => $this->valid_to && $this->valid_to->isPast(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
