<?php

namespace App\Scheduling;

enum BookingFailure: string
{
    case ServiceNotOffered = 'service_not_offered';
    case InThePast = 'in_the_past';
    case OutsideAvailability = 'outside_availability';
    case SlotTaken = 'slot_taken';
    case CustomerBusy = 'customer_busy';

    public function message(): string
    {
        return match ($this) {
            self::CustomerBusy => 'Você já tem um agendamento que coincide com este horário. Escolha outro.',
            self::ServiceNotOffered => 'Este profissional não atende este serviço.',
            self::InThePast => 'Não é possível agendar em um horário que já passou.',
            self::OutsideAvailability => 'O profissional não atende neste horário.',
            self::SlotTaken => 'Este horário acabou de ser ocupado. Escolha outro.',
        };
    }
}
