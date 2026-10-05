<?php

namespace App\Notifications;

use App\Enums\ReminderKind;
use App\Scheduling\AppointmentSummary;

class UpcomingAppointment extends AppointmentNotification
{
    public function __construct(AppointmentSummary $summary, public readonly ReminderKind $kind)
    {
        parent::__construct($summary);
    }

    protected function subject(): string
    {
        return match ($this->kind) {
            ReminderKind::DayBefore => "Lembrete: amanhã às {$this->summary->time}",
            ReminderKind::HoursBefore => "Lembrete: hoje às {$this->summary->time}",
        };
    }

    protected function title(): string
    {
        return 'Lembrete do seu agendamento';
    }

    protected function intro(): string
    {
        return match ($this->kind) {
            ReminderKind::DayBefore => 'Seu atendimento é amanhã.',
            ReminderKind::HoursBefore => 'Seu atendimento é daqui a pouco.',
        };
    }

    protected function action(): array
    {
        return ['Não vai conseguir ir? Cancele aqui', $this->summary->manageUrl];
    }
}
