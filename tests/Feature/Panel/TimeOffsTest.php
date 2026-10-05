<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TimeOff;
use App\Scheduling\Availability;
use App\Scheduling\Slot;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Hoje: terça 10/11/2026, 8h (São Paulo).
 */
class TimeOffsTest extends PanelTestCase
{
    public function test_lists_upcoming_time_offs_in_local_time()
    {
        $this->timeOff($this->bruno, '2026-11-12 00:00', '2026-11-15 00:00', 'Férias');
        $this->timeOff($this->rafael, '2026-11-11 14:00', '2026-11-11 16:00');
        $this->timeOff($this->rafael, '2026-11-09 00:00', '2026-11-10 00:00'); // já passou
        $this->timeOff(Professional::factory()->create(), '2026-11-11 00:00', '2026-11-12 00:00'); // outro negócio

        $this->actingAs($this->owner)
            ->get('/painel/folgas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/time-offs')
                ->has('time_offs', 2)
                ->where('time_offs.0.professional', 'Rafael')
                ->where('time_offs.0.period', '11/11/2026, 14:00–16:00')
                ->where('time_offs.1.period', '12/11 a 14/11/2026 (dia inteiro)')
                ->where('time_offs.1.reason', 'Férias')
                ->has('professionals', 2)
            );
    }

    public function test_registers_whole_days_in_the_business_timezone()
    {
        $this->actingAs($this->owner)
            ->post('/painel/folgas', $this->payload(['start_date' => '2026-11-12', 'end_date' => '2026-11-13']))
            ->assertSessionHasNoErrors();

        // Meia-noite em São Paulo = 03:00 UTC.
        $this->assertSame(
            ['2026-11-12 03:00', '2026-11-14 03:00'],
            array_values((array) DB::selectOne("SELECT to_char(starts_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS s, to_char(ends_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS e FROM time_offs")),
        );
    }

    public function test_registered_time_off_removes_the_slots_from_the_public_page()
    {
        $service = Service::factory()->for($this->tenant)->create(['duration_minutes' => 60]);
        $this->bruno->services()->attach($service);
        $this->tenant->run(fn () => $this->bruno->workingHours()->create(['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '12:00']));

        $this->actingAs($this->owner)->post('/painel/folgas', $this->payload([
            'all_day' => false, 'start_date' => '2026-11-11', 'start_time' => '09:00', 'end_time' => '10:30',
        ]))->assertSessionHasNoErrors();

        $times = array_map(
            fn (Slot $slot) => $slot->startsAt->setTimezone(self::TIMEZONE)->format('H:i'),
            app(Availability::class)->slots($this->bruno, $service, '2026-11-11'),
        );
        $this->assertSame(['10:30', '10:45', '11:00'], $times);
    }

    public function test_validates_the_period()
    {
        $this->actingAs($this->owner);

        $this->post('/painel/folgas', $this->payload(['start_date' => '2026-11-13', 'end_date' => '2026-11-12']))
            ->assertSessionHasErrors('end_date');
        $this->post('/painel/folgas', $this->payload(['all_day' => false, 'start_date' => '2026-11-11', 'start_time' => '16:00', 'end_time' => '14:00']))
            ->assertSessionHasErrors('end_time');
        $this->post('/painel/folgas', $this->payload(['start_date' => '2026-11-12', 'end_date' => '2027-03-01']))
            ->assertSessionHasErrors(['end_date' => 'A folga pode ter no máximo 90 dias.']);

        $this->assertDatabaseCount('time_offs', 0);
    }

    public function test_warns_about_appointments_in_the_period_before_registering()
    {
        $appointment = $this->appointment($this->bruno, '10:00', date: '2026-11-12');

        $this->actingAs($this->owner)
            ->post('/painel/folgas', $this->payload(['start_date' => '2026-11-12', 'end_date' => '2026-11-12']))
            ->assertSessionHasErrors('conflicts');
        $this->assertDatabaseCount('time_offs', 0);

        $this->post('/painel/folgas', $this->payload(['start_date' => '2026-11-12', 'end_date' => '2026-11-12', 'confirm_conflicts' => true]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('time_offs', 1);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_cancelled_appointments_are_not_conflicts()
    {
        $this->appointment($this->bruno, '10:00', AppointmentStatus::Cancelled, date: '2026-11-12');

        $this->actingAs($this->owner)
            ->post('/painel/folgas', $this->payload(['start_date' => '2026-11-12', 'end_date' => '2026-11-12']))
            ->assertSessionHasNoErrors();
    }

    public function test_cannot_register_for_a_professional_of_another_business()
    {
        $alheio = Professional::factory()->create();

        $this->actingAs($this->owner)
            ->post('/painel/folgas', $this->payload(['professional_id' => $alheio->id]))
            ->assertSessionHasErrors('professional_id');
    }

    public function test_removes_a_time_off()
    {
        $timeOff = $this->timeOff($this->bruno, '2026-11-12 00:00', '2026-11-13 00:00');

        $this->actingAs($this->owner)->delete("/painel/folgas/{$timeOff->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($timeOff);
    }

    public function test_time_offs_of_another_business_are_not_found()
    {
        $alheio = $this->timeOff(Professional::factory()->create(), '2026-11-12 00:00', '2026-11-13 00:00');

        $this->actingAs($this->owner)->delete("/painel/folgas/{$alheio->id}")->assertNotFound();
    }

    public function test_staff_cannot_manage_time_offs()
    {
        $this->actingAs($this->staff)->get('/painel/folgas')->assertForbidden();
        $this->actingAs($this->staff)->post('/painel/folgas', $this->payload())->assertForbidden();
    }

    public function test_the_agenda_shows_the_time_off_of_the_day()
    {
        $this->timeOff($this->bruno, '2026-11-10 14:00', '2026-11-10 16:00', 'Consulta');
        $this->timeOff($this->rafael, '2026-11-09 00:00', '2026-11-12 00:00', 'Férias');

        $this->actingAs($this->owner)
            ->get('/painel/agenda?date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->where('professionals.0.name', 'Bruno')
                ->where('professionals.0.time_offs', [['period' => '14:00–16:00', 'reason' => 'Consulta']])
                ->where('professionals.1.time_offs', [['period' => 'Dia inteiro', 'reason' => 'Férias']])
            );
    }

    private function timeOff(Professional $professional, string $startsAt, string $endsAt, ?string $reason = null): TimeOff
    {
        return TimeOff::factory()->for($professional->tenant)->create([
            'professional_id' => $professional->id,
            'starts_at' => $this->localTime(...array_reverse(explode(' ', $startsAt))),
            'ends_at' => $this->localTime(...array_reverse(explode(' ', $endsAt))),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'professional_id' => $this->bruno->id,
            'all_day' => true,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'reason' => null,
            ...$overrides,
        ];
    }
}
