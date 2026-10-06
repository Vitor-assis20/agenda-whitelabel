<?php

namespace App\Http\Controllers\Booking;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Booking\Concerns\PresentsSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Scheduling\AppointmentNotifier;
use App\Scheduling\AppointmentSummary;
use App\Scheduling\Availability;
use App\Scheduling\BookAppointment;
use App\Scheduling\BookingFailed;
use App\Scheduling\ChangeAppointmentStatus;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    use PresentsSchedule;

    public function __construct(
        private readonly CurrentTenant $current,
        private readonly Availability $availability,
    ) {}

    public function show(): Response
    {
        $services = Service::query()
            ->where('is_active', true)
            ->whereHas('professionals', fn ($query) => $query->where('is_active', true))
            ->with(['professionals' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('booking/index', [
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price_cents' => $service->price_cents,
                'professionals' => $service->professionals
                    ->map(fn (Professional $professional) => $professional->only('id', 'name'))
                    ->values(),
            ]),
            'dates' => $this->presentDays($this->availability->bookableDays($this->timezone())),
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'date' => ['required', ...$this->bookableDateRules($this->availability->bookableDays($this->timezone()))],
        ]);

        // Consultas sob o TenantScope: ids de outro tenant resultam em 404.
        $service = Service::findOrFail($validated['service_id']);
        $professional = Professional::findOrFail($validated['professional_id']);

        return response()->json([
            'slots' => $this->presentSlots($this->availability->slots($professional, $service, $validated['date']), $this->timezone()),
        ]);
    }

    public function store(StoreBookingRequest $request, BookAppointment $book, AppointmentNotifier $notifier): RedirectResponse
    {
        try {
            // O cliente só é criado se a reserva der certo.
            $appointment = DB::transaction(function () use ($request, $book) {
                $customer = Customer::firstOrCreate(
                    ['email' => Str::lower($request->validated('email'))],
                    ['name' => $request->validated('name'), 'phone' => $request->validated('phone')],
                );

                return $book->handle(
                    Professional::findOrFail($request->integer('professional_id')),
                    Service::findOrFail($request->integer('service_id')),
                    $customer,
                    CarbonImmutable::parse($request->validated('starts_at')),
                    $request->validated('notes'),
                );
            });
        } catch (BookingFailed $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        // Depois do commit: se a reserva falhasse, nenhum e-mail teria saído.
        $notifier->booked($appointment);

        return redirect()->to(URL::signedRoute('booking.confirmation', $appointment))->with('just_booked', true);
    }

    /**
     * Página do agendamento para o cliente: confirmação logo após reservar e
     * destino dos links nos e-mails (detalhes e cancelamento).
     */
    public function confirmation(Request $request, Appointment $appointment): Response
    {
        return Inertia::render('booking/confirmation', [
            'appointment' => AppointmentSummary::of($appointment)->toPageProps(),
            'just_booked' => (bool) $request->session()->get('just_booked'),
            'just_rescheduled' => (bool) $request->session()->get('just_rescheduled'),
        ]);
    }

    public function cancel(Appointment $appointment, ChangeAppointmentStatus $change, AppointmentNotifier $notifier): RedirectResponse
    {
        if (! $appointment->cancellableByCustomer()) {
            throw ValidationException::withMessages([
                'appointment' => 'Este agendamento não pode mais ser cancelado pelo link. Entre em contato com o estabelecimento.',
            ]);
        }

        $change->handle($appointment, AppointmentStatus::Cancelled);
        $notifier->cancelled($appointment, byCustomer: true);

        return redirect()->to(URL::signedRoute('booking.confirmation', $appointment));
    }

    private function timezone(): string
    {
        return $this->current->get()->timezone;
    }
}
