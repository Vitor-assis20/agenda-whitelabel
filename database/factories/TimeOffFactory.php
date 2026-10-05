<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 30))->startOfDay();

        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => fn (array $attributes) => Professional::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->endOfDay(),
            'reason' => fake()->randomElement(['Folga', 'Férias', 'Curso']),
        ];
    }
}
