<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\TenantRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->seedTenant($owner, [
            'name' => 'Barbearia Navalha',
            'slug' => 'barbearia-navalha',
            'branding' => ['primary_color' => '#B45309', 'logo_url' => null],
        ], services: [
            ['name' => 'Corte', 'duration_minutes' => 30, 'price_cents' => 5000],
            ['name' => 'Barba', 'duration_minutes' => 30, 'price_cents' => 3500],
            ['name' => 'Corte + Barba', 'duration_minutes' => 60, 'price_cents' => 7500],
        ], professionals: ['Rafael Lima', 'Bruno Costa']);

        $this->seedTenant($owner, [
            'name' => 'Studio Zen',
            'slug' => 'studio-zen',
            'branding' => ['primary_color' => '#0F766E', 'logo_url' => null],
        ], services: [
            ['name' => 'Massagem relaxante', 'duration_minutes' => 60, 'price_cents' => 15000],
            ['name' => 'Drenagem linfática', 'duration_minutes' => 90, 'price_cents' => 18000],
        ], professionals: ['Ana Ribeiro']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $services
     * @param  list<string>  $professionals
     */
    private function seedTenant(User $owner, array $attributes, array $services, array $professionals): void
    {
        $tenant = Tenant::factory()->withUser($owner, TenantRole::Owner)->create($attributes);

        $tenant->run(function (Tenant $tenant) use ($services, $professionals) {
            $services = collect($services)->map(fn (array $service) => Service::create($service));
            $customers = Customer::factory()->count(8)->for($tenant)->create();

            foreach ($professionals as $name) {
                $professional = Professional::factory()->for($tenant)->create(['name' => $name]);
                $professional->services()->attach($services->pluck('id'));

                // Terça a sábado, das 9h às 18h.
                foreach (range(2, 6) as $weekday) {
                    $professional->workingHours()->create([
                        'weekday' => $weekday,
                        'starts_at' => '09:00',
                        'ends_at' => '18:00',
                    ]);
                }

                $this->seedAgenda($tenant, $professional, $services->all(), $customers->all());
            }
        });
    }

    /**
     * Preenche os próximos dias úteis com horários em sequência, sem sobreposição.
     *
     * @param  list<Service>  $services
     * @param  list<Customer>  $customers
     */
    private function seedAgenda(Tenant $tenant, Professional $professional, array $services, array $customers): void
    {
        for ($day = 1; $day <= 5; $day++) {
            // 9h no fuso do negócio, gravado em UTC.
            $cursor = Carbon::today($tenant->timezone)->addDays($day)->setTime(9, 0)->utc();

            for ($slot = 0; $slot < 4; $slot++) {
                $service = fake()->randomElement($services);
                $startsAt = $cursor->copy();
                $cursor->addMinutes($service->duration_minutes + fake()->randomElement([0, 30, 60]));

                Appointment::create([
                    'professional_id' => $professional->id,
                    'service_id' => $service->id,
                    'customer_id' => fake()->randomElement($customers)->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
                    'status' => fake()->randomElement([AppointmentStatus::Confirmed, AppointmentStatus::Confirmed, AppointmentStatus::Pending]),
                    'price_cents' => $service->price_cents,
                ]);
            }
        }
    }
}
