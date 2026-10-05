<?php

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Tenant;
use App\Notifications\UpcomingAppointment;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\Feature\Panel\PanelTestCase;

/**
 * Atendimento de referência: terça 10/11/2026, 10h (São Paulo).
 */
class AppointmentRemindersTest extends PanelTestCase
{
    public function test_sends_the_day_before_reminder()
    {
        Notification::fake();
        $this->bookedAt('2026-11-08 12:00');

        $this->runRemindersAt('2026-11-09 10:30');

        $this->assertReminders([ReminderKind::DayBefore]);
    }

    public function test_does_not_send_before_it_is_due()
    {
        Notification::fake();
        $this->bookedAt('2026-11-08 12:00');

        $this->runRemindersAt('2026-11-09 09:00');

        Notification::assertNothingSent();
    }

    public function test_never_sends_the_same_reminder_twice()
    {
        Notification::fake();
        $this->bookedAt('2026-11-08 12:00');

        $this->runRemindersAt('2026-11-09 10:30');
        $this->runRemindersAt('2026-11-09 10:35');

        Notification::assertSentOnDemandTimes(UpcomingAppointment::class, 1);
        $this->assertDatabaseCount('appointment_reminders', 1);
    }

    public function test_sends_both_reminders_at_their_times()
    {
        Notification::fake();
        $this->bookedAt('2026-11-08 12:00');

        $this->runRemindersAt('2026-11-09 10:30');
        $this->runRemindersAt('2026-11-10 08:30');

        $this->assertReminders([ReminderKind::DayBefore, ReminderKind::HoursBefore]);
    }

    public function test_a_late_scheduler_skips_the_stale_day_before_reminder()
    {
        Notification::fake();
        $this->bookedAt('2026-11-08 12:00');

        // Agendador parado durante a janela de 24h: volta a 1h30 do atendimento.
        $this->runRemindersAt('2026-11-10 08:30');

        $this->assertReminders([ReminderKind::HoursBefore]);
    }

    public function test_last_minute_bookings_only_get_the_reminders_that_still_make_sense()
    {
        Notification::fake();
        $this->bookedAt('2026-11-10 07:00'); // 3h antes: sem o de 24h

        $this->runRemindersAt('2026-11-10 08:30');

        $this->assertReminders([ReminderKind::HoursBefore]);
    }

    public function test_bookings_made_inside_the_last_window_get_no_reminder()
    {
        Notification::fake();
        $this->bookedAt('2026-11-10 08:15'); // 1h45 antes: a confirmação basta

        $this->runRemindersAt('2026-11-10 08:30');

        Notification::assertNothingSent();
    }

    public function test_cancelled_and_past_appointments_get_no_reminder()
    {
        Notification::fake();
        $this->travelTo($this->localTime('12:00', '2026-11-08'));
        $this->appointment($this->bruno, '10:00', AppointmentStatus::Cancelled);
        $this->appointment($this->bruno, '07:00', date: '2026-11-09');

        $this->runRemindersAt('2026-11-09 10:30');

        Notification::assertNothingSent();
    }

    public function test_covers_every_business()
    {
        Notification::fake();
        $this->travelTo($this->localTime('12:00', '2026-11-08'));
        $this->appointment($this->bruno, '10:00');
        $outro = Professional::factory()->for(Tenant::factory()->create(['timezone' => self::TIMEZONE]))->create();
        $this->appointment($outro, '10:00');

        $this->runRemindersAt('2026-11-09 10:30');

        Notification::assertSentOnDemandTimes(UpcomingAppointment::class, 2);
    }

    public function test_the_queued_email_is_delivered_with_the_business_brand()
    {
        // Sem fake: a fila síncrona serializa a notificação como o worker faria,
        // e o comando roda sem tenant no contexto.
        $appointment = $this->bookedAt('2026-11-08 12:00');

        $this->runRemindersAt('2026-11-09 10:30');

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        /** @var Email $email */
        $email = $messages->first()->getOriginalMessage();
        $customer = $appointment->customer()->withoutGlobalScopes()->first();

        $this->assertSame('Lembrete: amanhã às 10:00 · Barbearia', $email->getSubject());
        $this->assertSame('Barbearia', $email->getFrom()[0]->getName());
        $this->assertSame($customer->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString("/agendamentos/{$appointment->id}?signature=", $email->getHtmlBody());
        $this->assertStringNotContainsString(config('app.name'), $email->getHtmlBody());
    }

    private function bookedAt(string $localDateTime): Appointment
    {
        [$date, $time] = explode(' ', $localDateTime);
        $this->travelTo($this->localTime($time, $date));

        return $this->appointment($this->bruno, '10:00');
    }

    private function runRemindersAt(string $localDateTime): void
    {
        [$date, $time] = explode(' ', $localDateTime);
        $this->travelTo($this->localTime($time, $date));

        $this->artisan('appointments:send-reminders')->assertSuccessful();
    }

    /**
     * @param  list<ReminderKind>  $kinds
     */
    private function assertReminders(array $kinds): void
    {
        $sent = [];
        Notification::assertSentOnDemand(UpcomingAppointment::class, function (UpcomingAppointment $notification) use (&$sent) {
            $sent[] = $notification->kind;

            return true;
        });

        $this->assertSame($kinds, $sent);
    }
}
