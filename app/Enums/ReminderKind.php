<?php

namespace App\Enums;

enum ReminderKind: string
{
    case DayBefore = 'day_before';
    case HoursBefore = 'hours_before';

    /**
     * Antecedência do lembrete em relação ao início do atendimento.
     */
    public function hours(): int
    {
        return match ($this) {
            self::DayBefore => 24,
            self::HoursBefore => 2,
        };
    }

    /**
     * Abaixo desta antecedência o lembrete perde o sentido e o próximo, mais
     * curto, assume: evita um "é amanhã" atrasado a duas horas do atendimento.
     */
    public function supersededWithinHours(): int
    {
        return match ($this) {
            self::DayBefore => self::HoursBefore->hours(),
            self::HoursBefore => 0,
        };
    }
}
