<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NearMissResource extends JsonResource
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
            'severity'              => $this->severity,
            'description'           => $this->description,
            'potential_consequence' => $this->potential_consequence,
            'corrective_action'     => $this->corrective_action,
            'corrective_action_due' => $this->corrective_action_due?->format('Y-m-d'),
            'status'                => $this->status,
            'involved_people' => \App\Support\People::resolve($this->involved_people ?? []),
            'image'                 => $this->image,
            'image_url'             => app(\App\Services\TenantFileService::class)->url($this->image),
            'reported_by'           => $this->whenLoaded('reporter', fn() => [
                'id'   => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            // Position renvoyee comme un objet, ou `null` s'il n'y en a pas.
            // Trois champs plats a `null` obligeraient chaque client a verifier
            // lat ET lon avant d'afficher quoi que ce soit ; un objet absent est
            // une reponse sans ambiguite : cet evenement n'est pas situe.
            'location_point'        => $this->latitude === null ? null : [
                'latitude'    => $this->latitude,
                'longitude'   => $this->longitude,
                // Rayon d'incertitude en metres, tel que rapporte par l'appareil.
                // Une carte doit distinguer un point fiable d'une approximation.
                'accuracy'    => $this->location_accuracy,
                // Instant de la CAPTURE, distinct de created_at : un signalement
                // rempli hors ligne est enregistre bien apres avoir ete localise.
                'captured_at' => $this->location_captured_at?->toIso8601String(),
            ],
            'created_at'            => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
