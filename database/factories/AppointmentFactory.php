<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Profissional, serviço e cliente são criados no mesmo tenant do
     * agendamento, como exigem as chaves estrangeiras compostas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 30))->setTime(fake()->numberBetween(9, 17), 0);

        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => fn (array $attributes) => Professional::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'service_id' => fn (array $attributes) => Service::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => AppointmentStatus::Confirmed,
            'price_cents' => fake()->numberBetween(20, 300) * 100,
            'notes' => null,
        ];
    }

    /**
     * Ocupa o intervalo [início, início + minutos).
     */
    public function at(string $startsAt, int $minutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $start = Carbon::parse($startsAt),
            'ends_at' => $start->copy()->addMinutes($minutes),
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : null,
        ]);
    }
}
