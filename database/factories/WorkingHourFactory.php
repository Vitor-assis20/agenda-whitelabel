<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => fn (array $attributes) => Professional::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'weekday' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ];
    }
}
