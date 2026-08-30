<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue AGENT d'un employé : identité professionnelle minimale, RIEN d'autre.
 *
 * Contrat volontairement minuscule — matricule, nom complet, statut. Construire la
 * charge explicitement (et non via l'EmployeeResource complet) garantit qu'aucun
 * champ sensible (email, téléphone, adresse, CNI, date de naissance, contrat,
 * département, compteurs HSE…) ne puisse fuiter par inadvertance : un nouveau champ
 * n'apparaît que si quelqu'un l'ajoute ICI. Aucun identifiant interne non plus.
 *
 * Statut : dérivé de `is_active` (seule notion de statut du modèle Employee). On
 * n'invente pas de valeur : le modèle ne connaît qu'actif / inactif.
 */
class AgentEmployeeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'matricule' => $this->matricule,
            'name'      => trim("{$this->prenom} {$this->nom}"),
            'status'    => $this->is_active ? 'active' : 'inactive',
        ];
    }
}
