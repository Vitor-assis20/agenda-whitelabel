<?php

namespace App\Http\Controllers\Booking;

use App\Enums\AppointmentStatus;
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
use App\Scheduling\Slot;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    /**
     * Quantos dias à frente o cliente pode agendar.
     */
    private const DAYS_AHEAD = 14;

    private const LOCALE = 'pt_BR';

    public function __construct(private readonly CurrentTenant $current) {}

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
            'dates' => $this->bookableDates()->map(fn (CarbonImmutable $date) => [
                'value' => $date->toDateString(),
                'weekday' => Str::ucfirst(rtrim($date->translatedFormat('D'), '.')),
                'day' => $date->format('d'),
                'month' => rtrim($date->translatedFormat('M'), '.'),
            ]),
        ]);
    }

    public function slots(Request $request, Availability $availability): JsonResponse
    {
        $dates = $this->bookableDates();

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$dates->first()->toDateString(),
                'before_or_equal:'.$dates->last()->toDateString(),
            ],
        ]);

        // Consultas sob o TenantScope: ids de outro tenant resultam em 404.
        $service = Service::findOrFail($validated['service_id']);
        $professional = Professional::findOrFail($validated['professional_id']);

        $slots = $availability->slots($professional, $service, $validated['date']);

        return response()->json([
            'slots' => array_map(fn (Slot $slot) => [
                'starts_at' => $slot->startsAt->toIso8601ZuluString(),
                'time' => $slot->startsAt->setTimezone($this->timezone())->format('H:i'),
            ], $slots),
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

    /**
     * Próximos dias a partir de hoje, no fuso do tenant.
     *
     * @return Collection<int, CarbonImmutable>
     */
    private function bookableDates(): Collection
    {
        $today = CarbonImmutable::now($this->timezone())->locale(self::LOCALE)->startOfDay();

        return collect(range(0, self::DAYS_AHEAD - 1))->map(fn (int $offset) => $today->addDays($offset));
    }

    private function timezone(): string
    {
        return $this->current->get()->timezone;
    }
}
