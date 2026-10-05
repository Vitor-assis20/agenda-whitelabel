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

        $barbearia = $this->seedTenant($owner, [
            'name' => 'Barbearia Navalha',
            'slug' => 'barbearia-navalha',
            'branding' => ['primary_color' => '#B45309', 'logo_path' => null],
        ], services: [
            ['name' => 'Corte', 'duration_minutes' => 30, 'price_cents' => 5000],
            ['name' => 'Barba', 'duration_minutes' => 30, 'price_cents' => 3500],
            ['name' => 'Corte + Barba', 'duration_minutes' => 60, 'price_cents' => 7500],
        ], professionals: ['Rafael Lima', 'Bruno Costa']);

        $this->seedTenant($owner, [
            'name' => 'Studio Zen',
            'slug' => 'studio-zen',
            'branding' => ['primary_color' => '#0F766E', 'logo_path' => null],
        ], services: [
            ['name' => 'Massagem relaxante', 'duration_minutes' => 60, 'price_cents' => 15000],
            ['name' => 'Drenagem linfática', 'duration_minutes' => 90, 'price_cents' => 18000],
        ], professionals: ['Ana Ribeiro']);

        // Membro da equipe: no painel, vê e gerencia só a própria agenda.
        $staff = User::factory()->create(['name' => 'Rafael Lima', 'email' => 'rafael@example.com']);
        $barbearia->users()->attach($staff, ['role' => TenantRole::Staff->value]);
        $barbearia->professionals()->where('name', 'Rafael Lima')->update(['user_id' => $staff->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $services
     * @param  list<string>  $professionals
     */
    private function seedTenant(User $owner, array $attributes, array $services, array $professionals): Tenant
    {
        $tenant = Tenant::factory()->withUser($owner, TenantRole::Owner)->create($attributes);

        $tenant->run(function (Tenant $tenant) use ($services, $professionals) {
            $services = collect($services)->map(fn (array $service) => Service::create($service));
            $customers = Customer::factory()->count(8)->for($tenant)->create();

            foreach ($professionals as $name) {
                $professional = Professional::factory()->for($tenant)->create(['name' => $name]);
                $professional->services()->attach($services->pluck('id'));

                // Segunda a sábado, das 9h às 18h.
                foreach (range(1, 6) as $weekday) {
                    $professional->workingHours()->create([
                        'weekday' => $weekday,
                        'starts_at' => '09:00',
                        'ends_at' => '18:00',
                    ]);
                }

                $this->seedAgenda($tenant, $professional, $services->all(), $customers->all());
            }
        });

        return $tenant;
    }

    /**
     * Preenche dos dois dias anteriores aos cinco seguintes com horários em
     * sequência, sem sobreposição. O passado já vem com o desfecho registrado.
     *
     * @param  list<Service>  $services
     * @param  list<Customer>  $customers
     */
    private function seedAgenda(Tenant $tenant, Professional $professional, array $services, array $customers): void
    {
        for ($day = -2; $day <= 5; $day++) {
            if (Carbon::today($tenant->timezone)->addDays($day)->isSunday()) {
                continue;
            }

            $statuses = $day < 0
                ? [AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::NoShow, AppointmentStatus::Cancelled]
                : [AppointmentStatus::Confirmed, AppointmentStatus::Confirmed, AppointmentStatus::Pending];

            // 9h no fuso do negócio; o cast UtcDateTime converte ao gravar.
            $cursor = Carbon::today($tenant->timezone)->addDays($day)->setTime(9, 0);

            for ($slot = 0; $slot < 4; $slot++) {
                $service = fake()->randomElement($services);
                $startsAt = $cursor->copy();
                $cursor->addMinutes($service->duration_minutes + fake()->randomElement([0, 30, 60]));

                // forceCreate: created_at no passado simula uma reserva feita com
                // antecedência, como na vida real (e habilita os lembretes na demo).
                Appointment::forceCreate([
                    'professional_id' => $professional->id,
                    'service_id' => $service->id,
                    'customer_id' => fake()->randomElement($customers)->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
                    'status' => $status = fake()->randomElement($statuses),
                    'cancelled_at' => $status === AppointmentStatus::Cancelled ? $startsAt->copy()->subDay() : null,
                    'price_cents' => $service->price_cents,
                    'created_at' => $startsAt->copy()->subDays(3)->utc(),
                ]);
            }
        }
    }
}
