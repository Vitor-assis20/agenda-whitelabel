<?php

namespace App\Notifications;

use App\Scheduling\AppointmentSummary;

class AppointmentCancelled extends AppointmentNotification
{
    public function __construct(AppointmentSummary $summary, public readonly bool $byCustomer)
    {
        parent::__construct($summary);
    }

    protected function subject(): string
    {
        return 'Agendamento cancelado';
    }

    protected function title(): string
    {
        return 'Agendamento cancelado';
    }

    protected function intro(): string
    {
        return $this->byCustomer
            ? 'Seu cancelamento foi registrado.'
            : 'Infelizmente o seu agendamento precisou ser cancelado pelo estabelecimento.';
    }

    protected function action(): array
    {
        return ['Agendar outro horário', $this->summary->bookingUrl];
    }
}
