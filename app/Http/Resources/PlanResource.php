<?php

namespace App\Http\Resources;

use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fx   = app(ExchangeRateService::class);
        $rate = $fx->get();

        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'name'             => $this->name,
            'description'      => $this->description,

            // EUR = source de vérité (centimes).
            'currency'         => $this->currency,
            'price_monthly'    => $this->price_monthly,
            'price_yearly'     => $this->price_yearly,

            'contact_sales'    => $this->contact_sales,
            'max_employees'    => $this->max_employees,
            'storage_limit_mb' => $this->storage_limit_mb,
            'features'         => $this->features ?? [],
            'active'           => $this->active,

            // Équivalence MRU INDICATIVE (affichage uniquement, jamais un montant d'autorité).
            'display_mru'      => [
                'currency'                 => $rate['quote'],
                'monthly'                  => $fx->displayEquivalent($this->price_monthly),
                'yearly'                   => $fx->displayEquivalent($this->price_yearly),
                'exchange_rate'            => $rate['rate'],
                'exchange_rate_updated_at' => $rate['updated_at'],
            ],
        ];
    }
}
