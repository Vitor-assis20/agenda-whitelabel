<?php

namespace App\Scheduling;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Move um agendamento para outro horário com o mesmo profissional e serviço.
 *
 * As mesmas garantias da reserva: checagens na aplicação (ignorando o
 * horário atual do próprio agendamento) e as exclusion constraints no
 * UPDATE, que barram uma corrida com outra reserva.
 */
class RescheduleAppointment
{
    public function __construct(private readonly Availability $availability) {}

    /**
     * @throws BookingFailed
     */
    public function handle(Appointment $appointment, CarbonInterface $startsAt): Appointment
    {
        return $appointment->tenant->run(function () use ($appointment, $startsAt) {
            if (! $appointment->cancellableByCustomer()) {
                throw new BookingFailed(BookingFailure::NotReschedulable);
            }

            $appointment->loadMissing('professional', 'service', 'customer');
            [$professional, $service, $customer] = [$appointment->professional, $appointment->service, $appointment->customer];

            if ($failure = $this->availability->check($professional, $service, $startsAt, ignoring: $appointment)) {
                throw new BookingFailed($failure);
            }

            if ($this->availability->customerIsBusy($customer, $service, $startsAt, ignoring: $appointment)) {
                throw new BookingFailed(BookingFailure::CustomerBusy);
            }

            $start = CarbonImmutable::instance($startsAt);

            try {
                DB::transaction(function () use ($appointment, $start, $service) {
                    $appointment->update([
                        'starts_at' => $start,
                        'ends_at' => $start->addMinutes($service->duration_minutes),
                        // Os lembretes passam a contar a partir da remarcação.
                        'booked_at' => now(),
                    ]);

                    // Lembretes do horário antigo não valem para o novo.
                    $appointment->reminders()->delete();
                });
            } catch (QueryException $e) {
                throw ($failure = ScheduleConflict::from($e)) ? new BookingFailed($failure, $e) : $e;
            }

            return $appointment;
        });
    }
}
