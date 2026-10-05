<?php

namespace Tests\Feature\Panel;

use App\Models\Professional;
use App\Models\Service;
use App\Scheduling\Availability;
use App\Scheduling\Slot;

class WorkingHoursTest extends PanelTestCase
{
    public function test_replaces_the_whole_week()
    {
        $this->tenant->run(fn () => $this->bruno->workingHours()->create(['weekday' => 5, 'starts_at' => '09:00', 'ends_at' => '18:00']));

        $this->actingAs($this->owner)
            ->put($this->url($this->bruno), ['hours' => [
                ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                ['weekday' => 2, 'starts_at' => '13:00', 'ends_at' => '18:00'],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [[2, '09:00:00', '12:00:00'], [2, '13:00:00', '18:00:00']],
            $this->tenant->run(fn () => $this->bruno->workingHours()->orderBy('starts_at')->get()
                ->map(fn ($hours) => [$hours->weekday, $hours->starts_at, $hours->ends_at])->all()),
        );
    }

    public function test_an_empty_week_removes_all_working_hours()
    {
        $this->tenant->run(fn () => $this->bruno->workingHours()->create(['weekday' => 5, 'starts_at' => '09:00', 'ends_at' => '18:00']));

        $this->actingAs($this->owner)->put($this->url($this->bruno), ['hours' => []])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('working_hours', 0);
    }

    public function test_rejects_intervals_that_end_before_they_start()
    {
        $this->actingAs($this->owner)
            ->put($this->url($this->bruno), ['hours' => [['weekday' => 2, 'starts_at' => '18:00', 'ends_at' => '09:00']]])
            ->assertSessionHasErrors(['hours.0.ends_at' => 'O fim deve ser depois do início.']);
    }

    public function test_rejects_overlapping_intervals_on_the_same_day()
    {
        $this->actingAs($this->owner)
            ->put($this->url($this->bruno), ['hours' => [
                ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                ['weekday' => 3, 'starts_at' => '11:00', 'ends_at' => '14:00'],
                ['weekday' => 2, 'starts_at' => '11:30', 'ends_at' => '14:00'],
            ]])
            ->assertSessionHasErrors(['hours.2.starts_at' => 'Este intervalo se sobrepõe a outro do mesmo dia.']);

        $this->assertDatabaseCount('working_hours', 0);
    }

    public function test_new_working_hours_are_offered_on_the_public_page()
    {
        $service = Service::factory()->for($this->tenant)->create(['duration_minutes' => 60]);
        $this->bruno->services()->attach($service);

        $this->actingAs($this->owner)->put($this->url($this->bruno), ['hours' => [
            ['weekday' => 2, 'starts_at' => '14:00', 'ends_at' => '16:00'],
        ]]);

        $times = array_map(
            fn (Slot $slot) => $slot->startsAt->setTimezone(self::TIMEZONE)->format('H:i'),
            app(Availability::class)->slots($this->bruno, $service, self::DATE),
        );
        $this->assertSame(['14:00', '14:15', '14:30', '14:45', '15:00'], $times);
    }

    public function test_professionals_of_another_business_are_not_found()
    {
        $alheio = Professional::factory()->create();

        $this->actingAs($this->owner)->put($this->url($alheio), ['hours' => []])->assertNotFound();
    }

    public function test_staff_cannot_change_working_hours()
    {
        $this->actingAs($this->staff)->put($this->url($this->rafael), ['hours' => []])->assertForbidden();
    }

    private function url(Professional $professional): string
    {
        return "/painel/profissionais/{$professional->id}/expediente";
    }
}
