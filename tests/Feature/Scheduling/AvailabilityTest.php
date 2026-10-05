<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Scheduling\Availability;
use App\Scheduling\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    // Terça-feira.
    private const DATE = '2026-11-10';

    private Professional $professional;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', self::TIMEZONE));

        $tenant = Tenant::factory()->create(['timezone' => self::TIMEZONE]);
        $this->professional = Professional::factory()->for($tenant)->create();
        $this->service = Service::factory()->for($tenant)->create(['duration_minutes' => 60]);
        $this->professional->services()->attach($this->service);
        $this->workingHours(2, '09:00', '12:00');
    }

    public function test_offers_every_interval_that_fits_in_the_working_hours()
    {
        $this->assertSame(
            ['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00'],
            $this->availableTimes(),
        );
    }

    public function test_slots_are_returned_in_utc()
    {
        $first = $this->availability()->slots($this->professional, $this->service, self::DATE)[0];

        $this->assertSame('2026-11-10 12:00:00', $first->startsAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $first->startsAt->tzName);
    }

    public function test_respects_breaks_between_working_windows()
    {
        $this->professional->tenant->run(fn () => $this->professional->workingHours()->delete());
        $this->workingHours(2, '09:00', '10:00');
        $this->workingHours(2, '14:00', '15:30');

        $this->assertSame(['09:00', '14:00', '14:15', '14:30'], $this->availableTimes());
    }

    public function test_hides_slots_overlapping_active_appointments()
    {
        $this->appointment('10:00', 60);
        $this->appointment('09:00', 60, AppointmentStatus::Cancelled);

        $this->assertSame(['09:00', '11:00'], $this->availableTimes());
    }

    public function test_hides_slots_overlapping_time_off()
    {
        TimeOff::factory()->for($this->professional->tenant)->create([
            'professional_id' => $this->professional->id,
            'starts_at' => CarbonImmutable::parse(self::DATE.' 09:00', self::TIMEZONE),
            'ends_at' => CarbonImmutable::parse(self::DATE.' 10:30', self::TIMEZONE),
        ]);

        $this->assertSame(['10:30', '10:45', '11:00'], $this->availableTimes());
    }

    public function test_hides_slots_that_already_started()
    {
        $this->travelTo(CarbonImmutable::parse(self::DATE.' 10:05', self::TIMEZONE));

        $this->assertSame(['10:15', '10:30', '10:45', '11:00'], $this->availableTimes());
    }

    public function test_is_empty_on_days_without_working_hours()
    {
        $this->assertSame([], $this->availableTimes('2026-11-09'));
    }

    public function test_is_empty_when_the_professional_does_not_offer_the_service()
    {
        $this->professional->services()->detach();

        $this->assertSame([], $this->availableTimes());
    }

    public function test_is_empty_when_the_professional_is_inactive()
    {
        $this->professional->update(['is_active' => false]);

        $this->assertSame([], $this->availableTimes());
    }

    private function availability(): Availability
    {
        return app(Availability::class);
    }

    /**
     * @return list<string>
     */
    private function availableTimes(string $date = self::DATE): array
    {
        return array_map(
            fn (Slot $slot) => $slot->startsAt->setTimezone(self::TIMEZONE)->format('H:i'),
            $this->availability()->slots($this->professional, $this->service, $date),
        );
    }

    private function workingHours(int $weekday, string $startsAt, string $endsAt): void
    {
        $this->professional->tenant->run(fn () => $this->professional->workingHours()->create([
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]));
    }

    private function appointment(string $time, int $minutes, AppointmentStatus $status = AppointmentStatus::Confirmed): void
    {
        $startsAt = CarbonImmutable::parse(self::DATE.' '.$time, self::TIMEZONE);

        Appointment::factory()->for($this->professional->tenant)->status($status)->create([
            'professional_id' => $this->professional->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($minutes),
        ]);
    }
}
