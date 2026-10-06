<?php

namespace App\Scheduling;

use Illuminate\Database\QueryException;

/**
 * Traduz a recusa das exclusion constraints de agendamento para o motivo de
 * negócio. É a última barreira quando duas operações passam juntas pelas
 * checagens da aplicação.
 */
final class ScheduleConflict
{
    private const EXCLUSION_VIOLATION = '23P01';

    private const CUSTOMER_CONSTRAINT = 'appointments_customer_no_overlap';

    /**
     * Motivo da recusa, ou null se a exceção não for de conflito de agenda.
     * O Postgres informa na mensagem qual constraint foi violada.
     */
    public static function from(QueryException $e): ?BookingFailure
    {
        if ($e->getCode() !== self::EXCLUSION_VIOLATION) {
            return null;
        }

        return str_contains($e->getMessage(), self::CUSTOMER_CONSTRAINT)
            ? BookingFailure::CustomerBusy
            : BookingFailure::SlotTaken;
    }
}
