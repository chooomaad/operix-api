<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractorResource extends JsonResource
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
            'company_name'     => $this->company_name,
            'contact_nom'      => $this->contact_nom,
            'contact_phone'    => $this->contact_phone,
            'contact_email'    => $this->contact_email,
            'activite'         => $this->activite,
            'num_registre'     => $this->num_registre,
            'contract_start'   => $this->contract_start?->format('Y-m-d'),
            'contract_end'     => $this->contract_end?->format('Y-m-d'),
            'status'           => $this->status,
            'is_expired'       => $this->isExpired(),
            'zones_autorisees' => $this->zones_autorisees,
            'logo'             => $this->logo,
            'logo_url'         => app(\App\Services\TenantFileService::class)->url($this->logo),
            'notes'            => $this->notes,
            'employees_count'  => $this->whenCounted('employees'),
            'created_at'       => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
