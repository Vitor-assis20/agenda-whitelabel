<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Notifications\AppointmentRescheduled;
use App\Notifications\UpcomingAppointment;
use App\Scheduling\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Agendamento de referência: terça 10/11/2026, 9h–10h (São Paulo), expediente 9h–12h.
 */
class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    private Tenant $tenant;

    private Professional $professional;

    private Service $service;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo($this->local('2026-11-01 12:00'));

        $this->tenant = Tenant::factory()->create(['slug' => 'barbearia', 'timezone' => self::TIMEZONE]);
        $this->professional = Professional::factory()->for($this->tenant)->create();
        $this->service = Service::factory()->for($this->tenant)->create(['duration_minutes' => 60]);
        $this->professional->services()->attach($this->service);
        $this->tenant->run(fn () => $this->professional->workingHours()->create(['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00']));

        $this->appointment = $this->appointmentAt('2026-11-10 09:00');
    }

    public function test_the_page_requires_a_valid_signature_but_accepts_a_chosen_date()
    {
        $this->get("http://barbearia.localhost/agendamentos/{$this->appointment->id}/remarcar")->assertForbidden();

        $this->get($this->pageUrl())->assertOk()->assertInertia(fn (Assert $page) => $page->component('booking/reschedule'));
        $this->get($this->pageUrl('2026-11-10'))->assertOk();
    }

    public function test_the_appointments_own_time_does_not_count_as_taken()
    {
        $this->get($this->pageUrl('2026-11-10'))->assertInertia(fn (Assert $page) => $page
            ->where('selected_date', '2026-11-10')
            ->has('slots', 9) // 09:00 a 11:00, inclusive os que se sobrepõem ao horário atual
            ->where('slots.1.time', '09:15')
        );
    }

    public function test_moves_the_appointment_and_emails_the_customer()
    {
        Notification::fake();
        $this->appointment->reminders()->create(['kind' => ReminderKind::DayBefore, 'sent_at' => now()]);

        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 10:30')])
            ->assertSessionHasNoErrors()
            ->assertRedirect(URL::signedRoute('booking.confirmation', ['tenant' => 'barbearia', 'appointment' => $this->appointment]));

        $moved = $this->appointment->fresh();
        $this->assertSame('10:30', $moved->starts_at->setTimezone(self::TIMEZONE)->format('H:i'));
        $this->assertSame('11:30', $moved->ends_at->setTimezone(self::TIMEZONE)->format('H:i'));
        $this->assertTrue($moved->booked_at->equalTo(now()));
        $this->assertSame(0, $moved->reminders()->count(), 'Lembretes do horário antigo devem ser descartados.');

        Notification::assertSentOnDemand(AppointmentRescheduled::class, fn (AppointmentRescheduled $notification) => $notification->previousTime === '09:00'
            && $notification->summary->time === '10:30');
    }

    public function test_the_page_confirms_the_reschedule()
    {
        $response = $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 10:30')]);

        $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
            ->where('just_rescheduled', true)
            ->where('appointment.time', '10:30')
        );
    }

    public function test_can_shift_over_its_own_current_time()
    {
        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 09:30')])->assertSessionHasNoErrors();

        $this->assertSame('09:30', $this->appointment->fresh()->starts_at->setTimezone(self::TIMEZONE)->format('H:i'));
    }

    public function test_rejects_a_taken_slot_and_keeps_the_original_time()
    {
        $this->appointmentAt('2026-11-10 11:00');

        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 10:30')])
            ->assertSessionHasErrors(['starts_at' => 'Este horário acabou de ser ocupado. Escolha outro.']);

        $this->assertSame('09:00', $this->appointment->fresh()->starts_at->setTimezone(self::TIMEZONE)->format('H:i'));
    }

    public function test_rejects_a_slot_outside_the_working_hours()
    {
        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 11:30')])
            ->assertSessionHasErrors(['starts_at' => 'O profissional não atende neste horário.']);
    }

    public function test_rejects_a_time_when_the_customer_is_busy_elsewhere()
    {
        Appointment::factory()->for($this->tenant)->create([
            'customer_id' => $this->appointment->customer_id,
            'starts_at' => $this->local('2026-11-10 10:30'),
            'ends_at' => $this->local('2026-11-10 11:30'),
        ]);

        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 10:30')])
            ->assertSessionHasErrors(['starts_at' => 'Você já tem um agendamento que coincide com este horário. Escolha outro.']);
    }

    public function test_a_concurrent_booking_is_stopped_by_the_database()
    {
        $this->mock(Availability::class, function ($mock) {
            $mock->shouldReceive('check')->andReturnNull();
            $mock->shouldReceive('customerIsBusy')->andReturnFalse();
        });
        $this->appointmentAt('2026-11-10 10:30');

        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 10:30')])
            ->assertSessionHasErrors(['starts_at' => 'Este horário acabou de ser ocupado. Escolha outro.']);

        $this->assertSame('09:00', $this->appointment->fresh()->starts_at->setTimezone(self::TIMEZONE)->format('H:i'));
    }

    public function test_cannot_reschedule_after_it_started_or_once_cancelled()
    {
        $this->travelTo($this->local('2026-11-10 09:10'));

        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 11:00')])->assertSessionHasErrors('starts_at');
        $this->get($this->pageUrl())->assertRedirect()->assertSessionHasErrors('appointment');

        $this->travelTo($this->local('2026-11-01 12:00'));
        $this->appointment->update(['status' => AppointmentStatus::Cancelled]);
        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 11:00')])->assertSessionHasErrors('starts_at');
    }

    public function test_submitting_requires_a_valid_signature()
    {
        $this->post("http://barbearia.localhost/agendamentos/{$this->appointment->id}/remarcar", ['starts_at' => $this->iso('2026-11-10 10:30')])
            ->assertForbidden();
    }

    public function test_reminders_follow_the_new_booking_time()
    {
        Notification::fake();

        // Remarcado na véspera para menos de 24h depois: sem o lembrete de 24h.
        $this->travelTo($this->local('2026-11-09 12:00'));
        $this->post($this->submitUrl(), ['starts_at' => $this->iso('2026-11-10 10:30')]);
        $this->artisan('appointments:send-reminders');
        Notification::assertSentOnDemandTimes(UpcomingAppointment::class, 0);

        // O de 2h continua valendo.
        $this->travelTo($this->local('2026-11-10 08:45'));
        $this->artisan('appointments:send-reminders');
        Notification::assertSentOnDemand(UpcomingAppointment::class, fn (UpcomingAppointment $n) => $n->kind === ReminderKind::HoursBefore);
    }

    private function appointmentAt(string $localDateTime): Appointment
    {
        $startsAt = $this->local($localDateTime);

        return Appointment::factory()->for($this->tenant)->create([
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);
    }

    private function pageUrl(?string $date = null): string
    {
        $url = URL::signedRoute('booking.reschedule', ['tenant' => 'barbearia', 'appointment' => $this->appointment]);

        return $date ? "{$url}&date={$date}" : $url;
    }

    private function submitUrl(): string
    {
        return URL::signedRoute('booking.reschedule.store', ['tenant' => 'barbearia', 'appointment' => $this->appointment]);
    }

    private function local(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TIMEZONE);
    }

    private function iso(string $localDateTime): string
    {
        return $this->local($localDateTime)->utc()->toIso8601ZuluString();
    }
}
