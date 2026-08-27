<?php

namespace App\Http\Requests\Concerns;

/**
 * Règles de validation de la position d'un évènement HSE.
 *
 * Partagées par les trois modules situables (incidents, presqu'accidents,
 * environnement) : une seule définition, donc une seule chose à corriger si la
 * règle évolue.
 *
 * PRINCIPE : mieux vaut aucune position qu'une position fausse. Une carte HSE
 * sert à envoyer des gens quelque part ; un point erroné est plus dangereux
 * qu'un point absent, parce qu'il inspire confiance.
 */
trait ValidatesGeolocation
{
    /**
     * Rayon d'incertitude au-delà duquel la « position » ne localise plus rien.
     *
     * 5 km correspond typiquement à une localisation déduite d'une antenne réseau,
     * pas d'un GPS. Sur un site industriel, cela ne distingue même pas deux quais :
     * l'afficher sur une carte laisserait croire à une précision inexistante.
     */
    private const MAX_ACCURACY_METERS = 5000;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function geolocationRules(): array
    {
        return [
            // `required_with` dans les deux sens : une latitude sans longitude ne
            // désigne aucun endroit. La contrainte CHECK en base applique la même
            // règle pour les écritures qui ne passeraient pas par ce formulaire.
            'latitude' => [
                'nullable',
                'required_with:longitude',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'required_with:latitude',
                'numeric',
                'between:-180,180',
            ],
            'location_accuracy' => [
                'nullable',
                'numeric',
                'min:0',
                'max:' . self::MAX_ACCURACY_METERS,
            ],
            // La date de capture vient de l'appareil, dont l'horloge peut dériver.
            // On refuse le futur (au-delà d'une tolérance de quelques minutes) sans
            // borner le passé : un signalement créé hors ligne et synchronisé
            // plusieurs jours plus tard porte légitimement une capture ancienne.
            'location_captured_at' => [
                'nullable',
                'date',
                'before_or_equal:' . now()->addMinutes(5)->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function geolocationMessages(): array
    {
        return [
            'latitude.required_with' => 'La latitude est requise dès qu\'une longitude est fournie.',
            'longitude.required_with' => 'La longitude est requise dès qu\'une latitude est fournie.',
            'latitude.between' => 'Latitude hors des bornes terrestres.',
            'longitude.between' => 'Longitude hors des bornes terrestres.',
            'location_accuracy.max' => 'Position trop imprécise pour être exploitable.',
            'location_captured_at.before_or_equal' => 'La date de capture ne peut pas être dans le futur.',
        ];
    }
}
