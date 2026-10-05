<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;

class AppointmentPolicy
{
    public function __construct(private readonly CurrentTenant $current) {}

    /**
     * Dono e administradores gerenciam qualquer agendamento do negócio;
     * a equipe, só os do próprio profissional.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        $role = $this->current->role();

        if ($role === null || $appointment->tenant_id !== $this->current->id()) {
            return false;
        }

        return $role->canManageTenant() || $appointment->professional->user_id === $user->id;
    }
}
