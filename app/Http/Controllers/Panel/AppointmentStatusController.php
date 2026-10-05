<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Scheduling\ChangeAppointmentStatus;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentStatusController extends Controller
{
    public function update(Request $request, Appointment $appointment, ChangeAppointmentStatus $change): RedirectResponse
    {
        Gate::authorize('update', $appointment);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        try {
            $change->handle($appointment, AppointmentStatus::from($validated['status']));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back();
    }
}
