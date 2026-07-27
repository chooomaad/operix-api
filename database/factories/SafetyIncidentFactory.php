<?php

namespace Database\Factories;

use App\Models\SafetyIncident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SafetyIncidentFactory extends Factory
{
    protected $model = SafetyIncident::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'reference'   => 'INC-' . date('Y') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'date'        => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'time'        => $this->faker->time('H:i'),
            'location'    => $this->faker->city(),
            'type'        => $this->faker->randomElement(['LTI', 'MTC', 'RWC', 'FAC', 'HPI', 'Fire', 'Security', 'Autre']),
            'severity'    => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'description' => $this->faker->sentence(),
            'status'      => 'open',
            'reported_by' => User::factory(),
        ];
    }
}
