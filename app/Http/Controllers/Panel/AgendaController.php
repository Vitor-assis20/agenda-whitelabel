<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\TimeOff;
use App\Scheduling\ChangeAppointmentStatus;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $current, ChangeAppointmentStatus $statuses): Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $timezone = $current->get()->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $day = $request->filled('date') ? CarbonImmutable::parse($request->query('date'), $timezone)->startOfDay() : $today;

        $ofTheDay = fn (Builder|HasMany $query) => $query
            ->where('starts_at', '>=', $day->utc())
            ->where('starts_at', '<', $day->addDay()->utc());

        $professionals = Professional::query()
            // A equipe vê só a própria agenda.
            ->unless($current->role()->canManageTenant(), fn (Builder $query) => $query->where('user_id', $request->user()->id))
            // Profissional desativado continua aparecendo se tiver atendimentos no dia.
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereHas('appointments', $ofTheDay))
            ->with([
                'appointments' => fn (HasMany $query) => $ofTheDay($query)->orderBy('starts_at')->with('service', 'customer'),
                'timeOffs' => fn (HasMany $query) => $query
                    ->where('starts_at', '<', $day->addDay()->utc())
                    ->where('ends_at', '>', $day->utc())
                    ->orderBy('starts_at'),
            ])
            ->orderBy('name')
            ->get();

        $appointments = $professionals->flatMap->appointments;

        return Inertia::render('panel/agenda', [
            'date' => [
                'value' => $day->toDateString(),
                'label' => Str::ucfirst($day->locale('pt_BR')->translatedFormat('l, j \d\e F')),
                'previous' => $day->subDay()->toDateString(),
                'next' => $day->addDay()->toDateString(),
                'is_today' => $day->equalTo($today),
            ],
            'summary' => [
                'appointments' => $appointments->filter(fn (Appointment $a) => $a->status->isBillable())->count(),
                'completed' => $appointments->where('status', AppointmentStatus::Completed)->count(),
                'expected_revenue_cents' => $appointments->filter(fn (Appointment $a) => $a->status->isBillable())->sum('price_cents'),
            ],
            'professionals' => $professionals->map(fn (Professional $professional) => [
                'id' => $professional->id,
                'name' => $professional->name,
                'time_offs' => $professional->timeOffs->map(fn (TimeOff $timeOff) => [
                    'period' => $this->periodWithinDay($timeOff, $day, $timezone),
                    'reason' => $timeOff->reason,
                ]),
                'appointments' => $professional->appointments->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'starts_at' => $appointment->starts_at->setTimezone($timezone)->format('H:i'),
                    'ends_at' => $appointment->ends_at->setTimezone($timezone)->format('H:i'),
                    'service' => $appointment->service->name,
                    'customer' => $appointment->customer->only('name', 'email', 'phone'),
                    'notes' => $appointment->notes,
                    'price_cents' => $appointment->price_cents,
                    'status' => $appointment->status->value,
                    // Ações já filtradas por permissão e pelo horário atual.
                    'actions' => $request->user()->can('update', $appointment->setRelation('professional', $professional))
                        ? array_map(fn (AppointmentStatus $status) => $status->value, $statuses->allowed($appointment))
                        : [],
                ]),
            ]),
        ]);
    }

    /**
     * A parte da folga que cai no dia exibido, em horário local.
     */
    private function periodWithinDay(TimeOff $timeOff, CarbonImmutable $day, string $timezone): string
    {
        $startsBefore = $timeOff->starts_at <= $day;
        $endsAfter = $timeOff->ends_at >= $day->addDay();
        $start = $timeOff->starts_at->setTimezone($timezone)->format('H:i');
        $end = $timeOff->ends_at->setTimezone($timezone)->format('H:i');

        return match (true) {
            $startsBefore && $endsAfter => 'Dia inteiro',
            $startsBefore => "Até {$end}",
            $endsAfter => "A partir das {$start}",
            default => "{$start}–{$end}",
        };
    }
}
