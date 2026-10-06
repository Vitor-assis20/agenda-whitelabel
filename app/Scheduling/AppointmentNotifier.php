<?php

namespace App\Scheduling;

use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentNotification;
use App\Notifications\AppointmentRescheduled;
use App\Notifications\BookingConfirmed;
use App\Notifications\UpcomingAppointment;
use Illuminate\Support\Facades\Notification;

/**
 * Envia ao cliente os e-mails do ciclo de vida do agendamento.
 *
 * Deve ser chamado depois do commit: e-mail enviado não volta atrás.
 */
class AppointmentNotifier
{
    public function booked(Appointment $appointment): void
    {
        $summary = AppointmentSummary::of($appointment);

        $this->send($summary, new BookingConfirmed($summary));
    }

    public function reminder(Appointment $appointment, ReminderKind $kind): void
    {
        $summary = AppointmentSummary::of($appointment);

        $this->send($summary, new UpcomingAppointment($summary, $kind));
    }

    public function rescheduled(Appointment $appointment, AppointmentSummary $before): void
    {
        $summary = AppointmentSummary::of($appointment);

        $this->send($summary, new AppointmentRescheduled($summary, $before->date, $before->time));
    }

    public function cancelled(Appointment $appointment, bool $byCustomer): void
    {
        $summary = AppointmentSummary::of($appointment);

        $this->send($summary, new AppointmentCancelled($summary, $byCustomer));
    }

    private function send(AppointmentSummary $summary, AppointmentNotification $notification): void
    {
        if (! $summary->customerEmail) {
            return;
        }

        // Destinatário avulso (e-mail + nome), não o model Customer: a fila
        // serializaria o model e não conseguiria recarregá-lo sem tenant.
        Notification::route('mail', [$summary->customerEmail => $summary->customerName])->notify($notification);
    }
}
