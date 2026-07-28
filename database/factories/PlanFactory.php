<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'slug'             => Str::slug($name) . '-' . Str::random(4),
            'name'             => ucfirst($name),
            'description'      => $this->faker->sentence(),
            'price_monthly'    => 4900,   // centimes EUR
            'price_yearly'     => 49000,
            'currency'         => 'EUR',
            'max_employees'    => 50,
            'storage_limit_mb' => 5120,
            'features'         => [],
            'contact_sales'    => false,
            'is_public'        => true,
            'sort_order'       => 0,
            'active'           => true,
        ];
    }

    public function contactSales(): static
    {
        return $this->state(fn () => [
            'price_monthly' => null,
            'price_yearly'  => null,
            'contact_sales' => true,
            'max_employees' => null,
        ]);
    }
}
