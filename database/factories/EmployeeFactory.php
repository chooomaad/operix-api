<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => Tenant::factory(),
            'matricule'    => strtoupper($this->faker->bothify('EMP-####')),
            'nom'          => $this->faker->lastName(),
            'prenom'       => $this->faker->firstName(),
            'email'        => $this->faker->unique()->safeEmail(),
            'phone'        => $this->faker->phoneNumber(),
            'poste'        => $this->faker->jobTitle(),
            'type_contrat' => $this->faker->randomElement(['CDI', 'CDD', 'Stage', 'Prestataire', 'Autre']),
            'date_embauche'=> $this->faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'is_active'    => true,
            'gender'       => $this->faker->randomElement(['M', 'F']),
        ];
    }
}
