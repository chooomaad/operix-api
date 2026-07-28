<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Données commerciales de base.
 *
 * ⚠️ PRIX PLACEHOLDER (en centimes EUR) — à VALIDER avant production. Les prix sont
 * administrables depuis le Super Admin ; ce seeder ne sert qu'à amorcer un jeu cohérent.
 * Le taux EUR→MRU est également indicatif et devra être mis à jour avec le taux réel.
 */
class CommercialSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter', 'name' => 'Starter', 'sort_order' => 1,
                'description' => 'Pour démarrer la gestion HSSE.',
                'price_monthly' => 4900, 'price_yearly' => 49000,   // PLACEHOLDER
                'max_employees' => 50, 'storage_limit_mb' => 5120,
                'features' => ['incidents', 'near_miss', 'employees'],
                'contact_sales' => false,
            ],
            [
                'slug' => 'business', 'name' => 'Business', 'sort_order' => 2,
                'description' => 'Pour les équipes HSSE complètes.',
                'price_monthly' => 12900, 'price_yearly' => 129000, // PLACEHOLDER
                'max_employees' => 500, 'storage_limit_mb' => 51200,
                'features' => ['incidents', 'near_miss', 'employees', 'permits', 'gemba', 'audit', 'exports'],
                'contact_sales' => false,
            ],
            [
                'slug' => 'enterprise', 'name' => 'Enterprise', 'sort_order' => 3,
                'description' => 'Sur devis, adapté aux grands comptes.',
                'price_monthly' => null, 'price_yearly' => null,
                'max_employees' => null, 'storage_limit_mb' => null,
                'features' => ['*'],
                'contact_sales' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan + ['currency' => 'EUR', 'is_public' => true, 'active' => true]);
        }

        // Taux EUR→MRU indicatif (PLACEHOLDER).
        ExchangeRate::updateOrCreate(
            ['base_currency' => 'EUR', 'quote_currency' => 'MRU'],
            ['rate' => 43.0]
        );
    }
}
