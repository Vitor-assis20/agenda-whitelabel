<?php

namespace App\Notifications;

use App\Scheduling\AppointmentSummary;

class AppointmentRescheduled extends AppointmentNotification
{
    public function __construct(
        AppointmentSummary $summary,
        public readonly string $previousDate,
        public readonly string $previousTime,
    ) {
        parent::__construct($summary);
    }

    protected function subject(): string
    {
        return 'Agendamento remarcado';
    }

    protected function title(): string
    {
        return 'Agendamento remarcado';
    }

    protected function intro(): string
    {
        return "Seu horário de {$this->previousDate} às {$this->previousTime} foi trocado. O novo horário é:";
    }

    protected function action(): array
    {
        return ['Ver ou alterar agendamento', $this->summary->manageUrl];
    }

    protected function outro(): string
    {
        return 'Os lembretes passam a valer para o novo horário.';
    }
}
