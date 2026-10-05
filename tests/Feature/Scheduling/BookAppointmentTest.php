<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Scheduling\Availability;
use App\Scheduling\BookAppointment;
use App\Scheduling\BookingFailed;
use App\Scheduling\BookingFailure;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class BookAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    private Professional $professional;

    private Service $service;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', self::TIMEZONE));

        $tenant = Tenant::factory()->create(['timezone' => self::TIMEZONE]);
        $this->professional = Professional::factory()->for($tenant)->create();
        $this->service = Service::factory()->for($tenant)->create(['duration_minutes' => 60, 'price_cents' => 8000]);
        $this->customer = Customer::factory()->for($tenant)->create();
        $this->professional->services()->attach($this->service);

        // Terça-feira, 9h às 12h.
        $tenant->run(fn () => $this->professional->workingHours()->create([
            'weekday' => 2,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]));
    }

    public function test_books_a_free_slot()
    {
        $appointment = $this->book('2026-11-10 10:00');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame($this->professional->tenant_id, $appointment->tenant_id);
        $this->assertSame(8000, $appointment->price_cents);
        $this->assertSame('2026-11-10 11:00', $appointment->ends_at->setTimezone(self::TIMEZONE)->format('Y-m-d H:i'));
    }

    public function test_stores_the_absolute_instant_regardless_of_the_input_timezone()
    {
        $appointment = $this->book('2026-11-10 09:00');

        $storedUtc = DB::scalar(
            "SELECT to_char(starts_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') FROM appointments WHERE id = ?",
            [$appointment->id],
        );

        $this->assertSame('2026-11-10 12:00', $storedUtc);
    }

    public function test_price_is_frozen_at_booking_time()
    {
        $appointment = $this->book('2026-11-10 10:00');

        $this->service->update(['price_cents' => 9900]);

        $this->assertSame(8000, $appointment->fresh()->price_cents);
    }

    public function test_rejects_a_taken_slot()
    {
        $this->book('2026-11-10 10:00');

        $this->assertBookingFails(BookingFailure::SlotTaken, '2026-11-10 10:30');
    }

    public function test_rejects_a_slot_that_ends_after_closing_time()
    {
        $this->assertBookingFails(BookingFailure::OutsideAvailability, '2026-11-10 11:30');
    }

    public function test_rejects_a_slot_during_time_off()
    {
        TimeOff::factory()->for($this->professional->tenant)->create([
            'professional_id' => $this->professional->id,
            'starts_at' => CarbonImmutable::parse('2026-11-10 00:00', self::TIMEZONE),
            'ends_at' => CarbonImmutable::parse('2026-11-11 00:00', self::TIMEZONE),
        ]);

        $this->assertBookingFails(BookingFailure::OutsideAvailability, '2026-11-10 10:00');
    }

    public function test_rejects_a_slot_in_the_past()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:30', self::TIMEZONE));

        $this->assertBookingFails(BookingFailure::InThePast, '2026-11-10 10:00');
    }

    public function test_rejects_a_service_the_professional_does_not_offer()
    {
        $this->professional->services()->detach();

        $this->assertBookingFails(BookingFailure::ServiceNotOffered, '2026-11-10 10:00');
    }

    public function test_rejects_a_customer_from_another_tenant()
    {
        $this->customer = Customer::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->book('2026-11-10 10:00');
    }

    public function test_a_concurrent_booking_that_passes_the_check_is_stopped_by_the_database()
    {
        // Simula outra requisição que reservou o horário entre a checagem e o INSERT.
        $this->mock(Availability::class, function ($mock) {
            $mock->shouldReceive('check')->andReturnNull();
            $mock->shouldReceive('customerIsBusy')->andReturnFalse();
        });
        Appointment::factory()->for($this->professional->tenant)->create([
            'professional_id' => $this->professional->id,
            'starts_at' => CarbonImmutable::parse('2026-11-10 10:00', self::TIMEZONE),
            'ends_at' => CarbonImmutable::parse('2026-11-10 11:00', self::TIMEZONE),
        ]);

        try {
            $this->book('2026-11-10 10:30');
            $this->fail('A reserva concorrente deveria ter sido rejeitada.');
        } catch (BookingFailed $e) {
            $this->assertSame(BookingFailure::SlotTaken, $e->reason);
            $this->assertInstanceOf(QueryException::class, $e->getPrevious());
        }

        $this->assertSame(1, Appointment::withoutGlobalScopes()->count());
    }

    public function test_rejects_a_customer_who_already_has_an_appointment_at_that_time()
    {
        // Mesmo cliente, outro profissional, horário sobreposto.
        Appointment::factory()->for($this->professional->tenant)->create([
            'customer_id' => $this->customer->id,
            'starts_at' => CarbonImmutable::parse('2026-11-10 09:30', self::TIMEZONE),
            'ends_at' => CarbonImmutable::parse('2026-11-10 10:30', self::TIMEZONE),
        ]);

        $this->assertBookingFails(BookingFailure::CustomerBusy, '2026-11-10 10:00');
        $this->book('2026-11-10 10:30'); // logo em seguida, tudo bem
    }

    public function test_a_concurrent_booking_by_the_same_customer_is_stopped_by_the_database()
    {
        // Simula a outra reserva do cliente gravada entre a checagem e o INSERT.
        $this->mock(Availability::class, function ($mock) {
            $mock->shouldReceive('check')->andReturnNull();
            $mock->shouldReceive('customerIsBusy')->andReturnFalse();
        });
        Appointment::factory()->for($this->professional->tenant)->create([
            'customer_id' => $this->customer->id,
            'starts_at' => CarbonImmutable::parse('2026-11-10 10:00', self::TIMEZONE),
            'ends_at' => CarbonImmutable::parse('2026-11-10 11:00', self::TIMEZONE),
        ]);

        try {
            $this->book('2026-11-10 10:30');
            $this->fail('A reserva simultânea do mesmo cliente deveria ter sido rejeitada.');
        } catch (BookingFailed $e) {
            // O profissional estava livre: quem barrou foi a constraint do cliente.
            $this->assertSame(BookingFailure::CustomerBusy, $e->reason);
        }
    }

    private function book(string $localTime): Appointment
    {
        return app(BookAppointment::class)->handle(
            $this->professional,
            $this->service,
            $this->customer,
            CarbonImmutable::parse($localTime, self::TIMEZONE),
        );
    }

    private function assertBookingFails(BookingFailure $expected, string $localTime): void
    {
        try {
            $this->book($localTime);
        } catch (BookingFailed $e) {
            $this->assertSame($expected, $e->reason);

            return;
        }

        $this->fail("A reserva deveria falhar com [{$expected->name}].");
    }
}
