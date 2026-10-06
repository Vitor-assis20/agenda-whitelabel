<?php

namespace App\Http\Controllers\Booking\Concerns;

use App\Scheduling\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Datas e horários no formato das páginas públicas (reserva e remarcação).
 */
trait PresentsSchedule
{
    /**
     * @param  Collection<int, CarbonImmutable>  $days
     * @return Collection<int, array<string, string>>
     */
    protected function presentDays(Collection $days): Collection
    {
        return $days->map(fn (CarbonImmutable $date) => [
            'value' => $date->toDateString(),
            'weekday' => Str::ucfirst(rtrim($date->translatedFormat('D'), '.')),
            'day' => $date->format('d'),
            'month' => rtrim($date->translatedFormat('M'), '.'),
        ])->values();
    }

    /**
     * @param  list<Slot>  $slots
     * @return list<array{starts_at: string, time: string}>
     */
    protected function presentSlots(array $slots, string $timezone): array
    {
        return array_map(fn (Slot $slot) => [
            'starts_at' => $slot->startsAt->toIso8601ZuluString(),
            'time' => $slot->startsAt->setTimezone($timezone)->format('H:i'),
        ], $slots);
    }

    /**
     * Regras de validação de uma data dentro da janela de agendamento.
     *
     * @param  Collection<int, CarbonImmutable>  $days
     * @return list<string>
     */
    protected function bookableDateRules(Collection $days): array
    {
        return [
            'date_format:Y-m-d',
            'after_or_equal:'.$days->first()->toDateString(),
            'before_or_equal:'.$days->last()->toDateString(),
        ];
    }
}
