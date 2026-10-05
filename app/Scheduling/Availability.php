<?php

namespace App\Scheduling;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Disponibilidade = expediente − folgas − agendamentos ativos.
 *
 * O expediente é cadastrado no fuso do tenant; todo o resto é comparado
 * em UTC, como instantes absolutos.
 */
class Availability
{
    /**
     * Espaçamento entre os horários oferecidos ao cliente.
     */
    public const INTERVAL_MINUTES = 15;

    /**
     * Horários livres para o serviço em um dia (data local do tenant, Y-m-d).
     *
     * @return list<Slot>
     */
    public function slots(Professional $professional, Service $service, string $date): array
    {
        if (! $this->performs($professional, $service)) {
            return [];
        }

        $day = CarbonImmutable::parse($date, $professional->tenant->timezone)->startOfDay();
        $schedule = $this->schedule($professional, $day);
        $slots = [];

        foreach ($schedule->windows as $window) {
            $start = $window->startsAt;

            while (($candidate = new Slot($start, $start->addMinutes($service->duration_minutes)))->within($window)) {
                if ($this->failureFor($candidate, $schedule) === null) {
                    $slots[] = $candidate;
                }

                $start = $start->addMinutes(self::INTERVAL_MINUTES);
            }
        }

        return $slots;
    }

    /**
     * Motivo pelo qual o horário não pode ser reservado, ou null se estiver livre.
     */
    public function check(Professional $professional, Service $service, CarbonInterface $startsAt): ?BookingFailure
    {
        if (! $this->performs($professional, $service)) {
            return BookingFailure::ServiceNotOffered;
        }

        $start = CarbonImmutable::instance($startsAt)->utc();
        $candidate = new Slot($start, $start->addMinutes($service->duration_minutes));
        $day = $start->setTimezone($professional->tenant->timezone)->startOfDay();

        return $this->failureFor($candidate, $this->schedule($professional, $day));
    }

    /**
     * O cliente já tem um agendamento ativo que coincide com o horário
     * (com qualquer profissional do negócio)?
     */
    public function customerIsBusy(Customer $customer, Service $service, CarbonInterface $startsAt): bool
    {
        $start = CarbonImmutable::instance($startsAt)->utc();

        return $customer->tenant->run(fn () => $customer->appointments()
            ->blocking()
            ->where('starts_at', '<', $start->addMinutes($service->duration_minutes))
            ->where('ends_at', '>', $start)
            ->exists());
    }

    private function performs(Professional $professional, Service $service): bool
    {
        return $professional->is_active
            && $service->is_active
            && $professional->tenant_id === $service->tenant_id
            && $professional->tenant->run(
                fn () => $professional->services()->whereKey($service->getKey())->exists()
            );
    }

    private function failureFor(Slot $candidate, DaySchedule $schedule): ?BookingFailure
    {
        $overlapsCandidate = fn (Slot $busy) => $candidate->overlaps($busy);

        return match (true) {
            $candidate->startsAt < now() => BookingFailure::InThePast,
            ! collect($schedule->windows)->contains(fn (Slot $window) => $candidate->within($window)) => BookingFailure::OutsideAvailability,
            collect($schedule->timeOffs)->contains($overlapsCandidate) => BookingFailure::OutsideAvailability,
            collect($schedule->appointments)->contains($overlapsCandidate) => BookingFailure::SlotTaken,
            default => null,
        };
    }

    /**
     * Carrega de uma vez tudo o que afeta a agenda do profissional no dia.
     *
     * @param  CarbonImmutable  $day  Início do dia, no fuso do tenant.
     */
    private function schedule(Professional $professional, CarbonImmutable $day): DaySchedule
    {
        $from = $day->utc();
        $to = $day->addDay()->utc();

        return $professional->tenant->run(fn () => new DaySchedule(
            windows: $professional->workingHours()
                ->where('weekday', $day->dayOfWeek)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (WorkingHour $hours) => new Slot(
                    $day->setTimeFromTimeString($hours->starts_at)->utc(),
                    $day->setTimeFromTimeString($hours->ends_at)->utc(),
                ))
                ->all(),
            timeOffs: $professional->timeOffs()
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from)
                ->get()
                ->map(fn (TimeOff $timeOff) => new Slot($timeOff->starts_at, $timeOff->ends_at))
                ->all(),
            appointments: $professional->appointments()
                ->blocking()
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from)
                ->get()
                ->map(fn (Appointment $appointment) => new Slot($appointment->starts_at, $appointment->ends_at))
                ->all(),
        ));
    }
}
