<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\TimeOffRequest;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\TimeOff;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TimeOffController extends Controller
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function index(): Response
    {
        $timeOffs = TimeOff::query()
            ->where('ends_at', '>', now())
            ->with('professional:id,name')
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('panel/time-offs', [
            'time_offs' => $timeOffs->map(fn (TimeOff $timeOff) => [
                'id' => $timeOff->id,
                'professional' => $timeOff->professional->name,
                'period' => self::describe($timeOff->starts_at, $timeOff->ends_at, $this->timezone()),
                'reason' => $timeOff->reason,
            ]),
            'professionals' => Professional::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(TimeOffRequest $request): RedirectResponse
    {
        [$startsAt, $endsAt] = $request->period($this->timezone());
        $conflicts = $this->conflictingAppointments($request->integer('professional_id'), $startsAt, $endsAt);

        // Os agendamentos não são cancelados aqui: o dono decide caso a caso na agenda.
        if ($conflicts->isNotEmpty() && ! $request->boolean('confirm_conflicts')) {
            // Mensagem única: o Inertia repassa só a primeira mensagem de cada campo.
            $list = $conflicts->map(fn (Appointment $appointment) => sprintf(
                '%s (%s)',
                $appointment->starts_at->setTimezone($this->timezone())->format('d/m H:i'),
                $appointment->customer->name,
            ))->join(', ');

            throw ValidationException::withMessages([
                'conflicts' => "Há {$conflicts->count()} agendamento(s) neste período: {$list}. Eles não serão cancelados automaticamente.",
            ]);
        }

        TimeOff::create([
            'professional_id' => $request->integer('professional_id'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $request->validated('reason'),
        ]);

        return back();
    }

    public function destroy(TimeOff $timeOff): RedirectResponse
    {
        $timeOff->delete();

        return back();
    }

    /**
     * Texto do período no fuso do negócio, reconhecendo dias inteiros.
     */
    public static function describe(CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone): string
    {
        $start = $startsAt->setTimezone($timezone);
        $end = $endsAt->setTimezone($timezone);

        if ($start->isStartOfDay() && $end->isStartOfDay()) {
            $lastDay = $end->subDay();

            return $start->isSameDay($lastDay)
                ? "{$start->format('d/m/Y')} (dia inteiro)"
                : "{$start->format('d/m')} a {$lastDay->format('d/m/Y')} (dia inteiro)";
        }

        return $start->isSameDay($end)
            ? "{$start->format('d/m/Y')}, {$start->format('H:i')}–{$end->format('H:i')}"
            : "{$start->format('d/m/Y H:i')} a {$end->format('d/m/Y H:i')}";
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function conflictingAppointments(int $professionalId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Collection
    {
        return Appointment::query()
            ->blocking()
            ->where('professional_id', $professionalId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->with('customer:id,name')
            ->orderBy('starts_at')
            ->get();
    }

    private function timezone(): string
    {
        return $this->current->get()->timezone;
    }
}
