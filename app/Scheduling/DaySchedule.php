<?php

namespace App\Scheduling;

/**
 * Agenda de um profissional em um dia, já convertida em intervalos UTC.
 */
final readonly class DaySchedule
{
    /**
     * @param  list<Slot>  $windows  Janelas de expediente.
     * @param  list<Slot>  $timeOffs  Folgas e bloqueios.
     * @param  list<Slot>  $appointments  Agendamentos que ocupam o horário.
     */
    public function __construct(
        public array $windows,
        public array $timeOffs,
        public array $appointments,
    ) {}
}
