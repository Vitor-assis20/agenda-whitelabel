<?php

namespace App\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use DomainException;

class ChangeAppointmentStatus
{
    /**
     * Status para os quais o agendamento pode ir agora.
     *
     * @return list<AppointmentStatus>
     */
    public function allowed(Appointment $appointment): array
    {
        $started = $appointment->starts_at <= now();

        return array_values(array_filter(
            $appointment->status->transitions(),
            fn (AppointmentStatus $status) => $started || ! $status->requiresStart(),
        ));
    }

    /**
     * @throws DomainException
     */
    public function handle(Appointment $appointment, AppointmentStatus $status): Appointment
    {
        if (! in_array($status, $this->allowed($appointment), true)) {
            throw new DomainException($this->refusal($appointment, $status));
        }

        // Voltar de cancelado para ativo não é uma transição permitida, então
        // nenhuma mudança aqui ocupa um horário: a exclusion constraint não dispara.
        $appointment->update([
            'status' => $status,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : $appointment->cancelled_at,
        ]);

        return $appointment;
    }

    private function refusal(Appointment $appointment, AppointmentStatus $status): string
    {
        if ($appointment->status->canTransitionTo($status) && $status->requiresStart()) {
            return "Só é possível marcar como \"{$status->label()}\" depois do horário de início.";
        }

        return "Um agendamento \"{$appointment->status->label()}\" não pode passar para \"{$status->label()}\".";
    }
}
