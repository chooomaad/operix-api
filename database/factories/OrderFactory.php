<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'plan_id'       => Plan::factory(),
            'company_name'  => $this->faker->company(),
            'contact_name'  => $this->faker->name(),
            'email'         => $this->faker->unique()->safeEmail(),
            'phone'         => $this->faker->phoneNumber(),
            'billing_cycle' => 'monthly',
            'amount'        => 4900,
            'currency'      => 'EUR',
            'status'        => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid', 'paid_at' => now()]);
    }
}
