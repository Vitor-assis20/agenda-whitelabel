<?php

namespace App\Console\Commands;

use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Scheduling\AppointmentNotifier;
use App\Support\Tenancy\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envia os lembretes de agendamento que estão no prazo';

    public function handle(AppointmentNotifier $notifier): int
    {
        $sent = 0;

        foreach (ReminderKind::cases() as $kind) {
            // Atravessa todos os tenants de propósito: o scope é removido explicitamente.
            $due = Appointment::withoutGlobalScope(TenantScope::class)
                ->blocking()
                ->where('starts_at', '>', now()->addHours($kind->supersededWithinHours()))
                ->where('starts_at', '<=', now()->addHours($kind->hours()))
                // Só se o agendamento já existia quando chegou a hora do lembrete:
                // quem reserva em cima da hora recebe só a confirmação.
                ->whereRaw('created_at <= starts_at - make_interval(hours => ?)', [$kind->hours()])
                ->whereDoesntHave('reminders', fn ($query) => $query->where('kind', $kind))
                ->with('tenant');

            foreach ($due->lazyById() as $appointment) {
                if ($this->claim($appointment, $kind)) {
                    $notifier->reminder($appointment, $kind);
                    $sent++;
                }
            }
        }

        $this->info("{$sent} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }

    /**
     * Registra o envio antes de enviar. Se outra execução já registrou,
     * o UNIQUE (appointment_id, kind) ignora a linha e não enviamos de novo.
     */
    private function claim(Appointment $appointment, ReminderKind $kind): bool
    {
        return DB::table('appointment_reminders')->insertOrIgnore([
            'appointment_id' => $appointment->id,
            'kind' => $kind->value,
            'sent_at' => now()->utc()->format('Y-m-d H:i:s'),
        ]) === 1;
    }
}
