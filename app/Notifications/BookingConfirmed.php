<?php

namespace App\Notifications;

class BookingConfirmed extends AppointmentNotification
{
    protected function subject(): string
    {
        return 'Agendamento confirmado';
    }

    protected function title(): string
    {
        return 'Agendamento confirmado';
    }

    protected function intro(): string
    {
        return 'Seu horário está reservado. Confira os detalhes:';
    }

    protected function action(): array
    {
        return ['Ver, remarcar ou cancelar', $this->summary->manageUrl];
    }

    protected function outro(): string
    {
        return 'Você vai receber um lembrete antes do atendimento.';
    }
}
