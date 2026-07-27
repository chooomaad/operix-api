<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(4),
            'country'       => 'MR',
            'timezone'      => 'Africa/Nouakchott',
            'locale'        => 'fr',
            'plan'          => 'starter',
            'status'        => 'active',
            'max_employees' => 100,
            'settings'      => [],
        ];
    }
}
