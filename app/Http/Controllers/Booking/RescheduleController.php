<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Booking\Concerns\PresentsSchedule;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Scheduling\AppointmentNotifier;
use App\Scheduling\AppointmentSummary;
use App\Scheduling\Availability;
use App\Scheduling\BookingFailed;
use App\Scheduling\RescheduleAppointment;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RescheduleController extends Controller
{
    use PresentsSchedule;

    public function __construct(
        private readonly CurrentTenant $current,
        private readonly Availability $availability,
    ) {}

    /**
     * Escolha do novo horário. O dia vem em ?date=, fora da assinatura do link:
     * a assinatura é conferida aqui ignorando só esse parâmetro.
     */
    public function show(Request $request, Appointment $appointment): Response|RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['date']), 403);

        if (! $appointment->cancellableByCustomer()) {
            return redirect()
                ->to(URL::signedRoute('booking.confirmation', $appointment))
                ->withErrors(['appointment' => 'Este agendamento não pode mais ser remarcado pelo link.']);
        }

        $days = $this->availability->bookableDays($this->timezone());
        $date = $request->validate(['date' => ['nullable', ...$this->bookableDateRules($days)]])['date'] ?? null;
        $appointment->load('professional', 'service');
        $summary = AppointmentSummary::of($appointment);

        return Inertia::render('booking/reschedule', [
            'appointment' => $summary->toPageProps(),
            'back_url' => $summary->manageUrl,
            'dates' => $this->presentDays($days),
            'selected_date' => $date,
            'slots' => $date
                ? $this->presentSlots(
                    $this->availability->slots($appointment->professional, $appointment->service, $date, ignoring: $appointment),
                    $this->timezone(),
                )
                : [],
            'submit_url' => URL::signedRoute('booking.reschedule.store', $appointment),
        ]);
    }

    public function store(Request $request, Appointment $appointment, RescheduleAppointment $reschedule, AppointmentNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate(['starts_at' => ['required', 'date']]);
        $before = AppointmentSummary::of($appointment);

        try {
            $reschedule->handle($appointment, CarbonImmutable::parse($validated['starts_at']));
        } catch (BookingFailed $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        $notifier->rescheduled($appointment, $before);

        return redirect()->to(URL::signedRoute('booking.confirmation', $appointment))->with('just_rescheduled', true);
    }

    private function timezone(): string
    {
        return $this->current->get()->timezone;
    }
}
