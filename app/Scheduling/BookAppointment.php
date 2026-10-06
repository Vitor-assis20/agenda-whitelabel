<?php

namespace App\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookAppointment
{
    public function __construct(private readonly Availability $availability) {}

    /**
     * @throws BookingFailed
     */
    public function handle(
        Professional $professional,
        Service $service,
        Customer $customer,
        CarbonInterface $startsAt,
        ?string $notes = null,
    ): Appointment {
        if ($customer->tenant_id !== $professional->tenant_id) {
            throw new InvalidArgumentException('O cliente pertence a outro tenant.');
        }

        if ($failure = $this->availability->check($professional, $service, $startsAt)) {
            throw new BookingFailed($failure);
        }

        if ($this->availability->customerIsBusy($customer, $service, $startsAt)) {
            throw new BookingFailed(BookingFailure::CustomerBusy);
        }

        $startsAt = CarbonImmutable::instance($startsAt);

        $appointment = new Appointment([
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($service->duration_minutes),
            'status' => AppointmentStatus::Confirmed,
            'price_cents' => $service->price_cents,
            'notes' => $notes,
        ]);
        $appointment->tenant_id = $professional->tenant_id;

        // As checagens acima cobrem o caso comum. Se duas reservas passarem por elas
        // ao mesmo tempo, as exclusion constraints barram a segunda.
        // O savepoint mantém utilizável uma transação externa após a falha.
        try {
            DB::transaction(fn () => $appointment->save());
        } catch (QueryException $e) {
            throw ($failure = ScheduleConflict::from($e)) ? new BookingFailed($failure, $e) : $e;
        }

        return $appointment;
    }
}
