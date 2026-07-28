<?php

namespace App\Services;

use App\Models\ExchangeRate;

/**
 * Fournit le taux de change pour l'équivalence d'affichage (EUR → MRU par défaut).
 *
 * Le taux vit en base (table exchange_rates) et peut être mis à jour ultérieurement.
 * Fallback config si aucune ligne. Ce taux est INDICATIF : il ne sert jamais à établir
 * le montant faisant autorité d'une commande (toujours calculé en EUR depuis le plan).
 */
class ExchangeRateService
{
    public function get(?string $base = null, ?string $quote = null): array
    {
        $base  = $base  ?? config('operix.commercial_currency', 'EUR');
        $quote = $quote ?? config('operix.display_currency', 'MRU');

        $row = ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->first();

        return [
            'base'       => $base,
            'quote'      => $quote,
            'rate'       => $row ? (float) $row->rate : (float) config('operix.default_display_rate', 0),
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Convertit un montant (centimes de la devise commerciale) en équivalent affichage
     * (unité entière de la devise d'affichage). Indicatif uniquement.
     */
    public function displayEquivalent(?int $minorAmount): ?int
    {
        if ($minorAmount === null) {
            return null;
        }

        $rate = $this->get()['rate'];
        if ($rate <= 0) {
            return null;
        }

        // centimes EUR → EUR → MRU (arrondi entier, indicatif)
        return (int) round(($minorAmount / 100) * $rate);
    }
}
