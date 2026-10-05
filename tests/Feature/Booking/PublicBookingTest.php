<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    private Tenant $tenant;

    private Professional $professional;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', self::TIMEZONE));

        $this->tenant = Tenant::factory()->create(['slug' => 'barbearia', 'name' => 'Barbearia Navalha', 'timezone' => self::TIMEZONE]);
        $this->professional = Professional::factory()->for($this->tenant)->create(['name' => 'Rafael']);
        $this->service = Service::factory()->for($this->tenant)->create(['name' => 'Corte', 'duration_minutes' => 60, 'price_cents' => 5000]);
        $this->professional->services()->attach($this->service);

        // Terça-feira, 9h às 12h.
        $this->tenant->run(fn () => $this->professional->workingHours()->create([
            'weekday' => 2,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]));
    }

    public function test_shows_the_booking_page_of_the_tenant_in_the_subdomain()
    {
        $this->get($this->url('/'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('booking/index')
                ->where('tenant.name', 'Barbearia Navalha')
                ->has('services', 1)
                ->where('services.0.name', 'Corte')
                ->where('services.0.professionals.0.name', 'Rafael')
                ->has('dates', 14)
                ->where('dates.0.value', '2026-11-01')
                ->where('dates.0.weekday', 'Dom')
            );
    }

    public function test_hides_inactive_services_and_services_without_active_professionals()
    {
        Service::factory()->for($this->tenant)->create(['is_active' => false]);
        $semProfissional = Service::factory()->for($this->tenant)->create();
        Professional::factory()->for($this->tenant)->inactive()->create()->services()->attach($semProfissional);

        $this->get($this->url('/'))
            ->assertInertia(fn (Assert $page) => $page->has('services', 1)->where('services.0.id', $this->service->id));
    }

    public function test_unknown_or_inactive_tenants_are_not_found()
    {
        Tenant::factory()->inactive()->create(['slug' => 'fechada']);

        $this->get('http://inexistente.localhost/')->assertNotFound();
        $this->get('http://fechada.localhost/')->assertNotFound();
    }

    public function test_lists_available_slots_with_local_times()
    {
        $this->getJson($this->slotsUrl('2026-11-10'))
            ->assertOk()
            ->assertJsonCount(9, 'slots')
            ->assertJsonPath('slots.0', ['starts_at' => '2026-11-10T12:00:00Z', 'time' => '09:00']);
    }

    public function test_slots_of_another_tenant_are_not_found()
    {
        $alheio = Service::factory()->create();

        $this->getJson($this->slotsUrl('2026-11-10', serviceId: $alheio->id))->assertNotFound();
    }

    public function test_slots_outside_the_booking_window_are_rejected()
    {
        $this->getJson($this->slotsUrl('2026-12-01'))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson($this->slotsUrl('2026-10-31'))->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_books_and_shows_the_confirmation()
    {
        $response = $this->post($this->url('agendar'), $this->booking(['email' => 'Joao@Example.com']));

        $appointment = Appointment::withoutGlobalScopes()->sole();
        $response->assertRedirect(URL::signedRoute('booking.confirmation', ['tenant' => 'barbearia', 'appointment' => $appointment]));
        $this->assertSame('joao@example.com', $appointment->customer()->withoutGlobalScopes()->first()->email);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('booking/confirmation')
                ->where('appointment.service', 'Corte')
                ->where('appointment.date', 'Terça-feira, 10 de novembro de 2026')
                ->where('appointment.time', '09:00')
                ->where('appointment.price_cents', 5000)
            );
    }

    public function test_returning_customers_are_matched_by_email()
    {
        $this->post($this->url('agendar'), $this->booking(['email' => 'joao@example.com']));
        $this->post($this->url('agendar'), $this->booking(['email' => 'JOAO@example.com', 'starts_at' => '2026-11-10T13:00:00Z']));

        $this->assertSame(1, Customer::withoutGlobalScopes()->count());
        $this->assertSame(2, Appointment::withoutGlobalScopes()->count());
    }

    public function test_a_taken_slot_returns_a_friendly_error_without_creating_the_customer()
    {
        $this->post($this->url('agendar'), $this->booking(['email' => 'primeiro@example.com']));

        $this->post($this->url('agendar'), $this->booking(['email' => 'segundo@example.com']))
            ->assertSessionHasErrors(['starts_at' => 'Este horário acabou de ser ocupado. Escolha outro.']);

        $this->assertSame(['primeiro@example.com'], Customer::withoutGlobalScopes()->pluck('email')->all());
    }

    public function test_cannot_book_a_service_of_another_tenant()
    {
        $alheio = Service::factory()->create();

        $this->post($this->url('agendar'), $this->booking(['service_id' => $alheio->id]))
            ->assertSessionHasErrors('service_id');
    }

    public function test_confirmation_requires_a_valid_signature()
    {
        $this->post($this->url('agendar'), $this->booking());
        $appointment = Appointment::withoutGlobalScopes()->sole();

        $this->get($this->url("agendamentos/{$appointment->id}"))->assertForbidden();
    }

    public function test_confirmation_of_another_tenant_is_not_found_even_when_signed()
    {
        $alheio = Appointment::factory()->create();

        $this->get(URL::signedRoute('booking.confirmation', ['tenant' => 'barbearia', 'appointment' => $alheio]))
            ->assertNotFound();
    }

    private function url(string $path): string
    {
        return 'http://barbearia.localhost/'.ltrim($path, '/');
    }

    private function slotsUrl(string $date, ?int $serviceId = null): string
    {
        return $this->url('horarios?'.http_build_query([
            'service_id' => $serviceId ?? $this->service->id,
            'professional_id' => $this->professional->id,
            'date' => $date,
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function booking(array $overrides = []): array
    {
        return [
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'starts_at' => '2026-11-10T12:00:00Z',
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '(48) 99999-0000',
            ...$overrides,
        ];
    }
}
