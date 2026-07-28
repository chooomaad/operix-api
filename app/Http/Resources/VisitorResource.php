<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nom'              => $this->nom,
            'prenom'           => $this->prenom,
            'full_name'        => $this->full_name,
            'entreprise'       => $this->entreprise,
            'phone'            => $this->phone,
            'email'            => $this->email,
            'cin'              => $this->cin,
            'badge_number'     => $this->badge_number,
            'motif'            => $this->motif,
            'personne_visitee' => $this->personne_visitee,
            'department'       => $this->department,
            'status'           => $this->status,
            'checked_in_at'    => $this->checked_in_at?->format('Y-m-d H:i'),
            'checked_out_at'   => $this->checked_out_at?->format('Y-m-d H:i'),
            'duration'         => $this->duration,
            'vehicle_plate'    => $this->vehicle_plate,
            'photo'            => $this->photo,
            'photo_url'        => app(\App\Services\TenantFileService::class)->url($this->photo),
            'notes'            => $this->notes,
            'created_at'       => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
