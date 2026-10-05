<?php

namespace App\Scheduling;

use App\Models\Appointment;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Agendamento já formatado para o cliente (página e e-mails).
 *
 * Só dados prontos, sem models: as notificações vão para a fila, e o worker
 * não tem tenant no contexto para recarregar models com escopo.
 */
final readonly class AppointmentSummary
{
    public function __construct(
        public int $appointmentId,
        public string $tenantName,
        public string $service,
        public string $professional,
        public string $customerName,
        public ?string $customerEmail,
        public string $date,
        public string $time,
        public int $durationMinutes,
        public int $priceCents,
        public string $status,
        public bool $cancellable,
        public string $manageUrl,
        public string $cancelUrl,
        public string $bookingUrl,
    ) {}

    /**
     * Monta o resumo dentro do tenant do agendamento, de onde quer que seja
     * chamado (request, comando agendado ou fila).
     */
    public static function of(Appointment $appointment): self
    {
        $tenant = $appointment->tenant;

        return $tenant->run(function () use ($appointment, $tenant) {
            $appointment->loadMissing('service', 'professional', 'customer');
            $startsAt = $appointment->starts_at->setTimezone($tenant->timezone)->locale('pt_BR');
            $parameters = ['tenant' => $tenant->slug, 'appointment' => $appointment->id];

            return new self(
                appointmentId: $appointment->id,
                tenantName: $tenant->name,
                service: $appointment->service->name,
                professional: $appointment->professional->name,
                customerName: $appointment->customer->name,
                customerEmail: $appointment->customer->email,
                date: Str::ucfirst($startsAt->translatedFormat('l, j \d\e F \d\e Y')),
                time: $startsAt->format('H:i'),
                durationMinutes: $appointment->service->duration_minutes,
                priceCents: $appointment->price_cents,
                status: $appointment->status->value,
                cancellable: $appointment->cancellableByCustomer(),
                manageUrl: URL::signedRoute('booking.confirmation', $parameters),
                cancelUrl: URL::signedRoute('booking.cancel', $parameters),
                bookingUrl: route('booking.show', ['tenant' => $tenant->slug]),
            );
        });
    }

    public function customerFirstName(): string
    {
        return Str::before($this->customerName, ' ');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPageProps(): array
    {
        return [
            'service' => $this->service,
            'professional' => $this->professional,
            'customer' => $this->customerName,
            'date' => $this->date,
            'time' => $this->time,
            'duration_minutes' => $this->durationMinutes,
            'price_cents' => $this->priceCents,
            'status' => $this->status,
            'cancel_url' => $this->cancellable ? $this->cancelUrl : null,
        ];
    }
}
