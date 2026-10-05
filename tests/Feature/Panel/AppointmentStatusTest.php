<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelled;
use Illuminate\Support\Facades\Notification;

class AppointmentStatusTest extends PanelTestCase
{
    public function test_owner_confirms_a_pending_appointment()
    {
        $appointment = $this->appointment($this->bruno, '10:00', AppointmentStatus::Pending);

        $this->actingAs($this->owner)
            ->patch($this->url($appointment), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_cannot_complete_an_appointment_before_it_starts()
    {
        $appointment = $this->appointment($this->bruno, '10:00');

        $this->actingAs($this->owner)
            ->patch($this->url($appointment), ['status' => 'completed'])
            ->assertSessionHasErrors(['status' => 'Só é possível marcar como "Concluído" depois do horário de início.']);

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_completes_an_appointment_after_it_starts()
    {
        $appointment = $this->appointment($this->bruno, '10:00');
        $this->travelTo($this->localTime('10:30'));

        $this->actingAs($this->owner)->patch($this->url($appointment), ['status' => 'completed']);

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_cancelling_records_when_and_frees_the_slot()
    {
        $appointment = $this->appointment($this->bruno, '10:00');

        $this->actingAs($this->owner)->patch($this->url($appointment), ['status' => 'cancelled']);

        $this->assertNotNull($appointment->fresh()->cancelled_at);
        // A exclusion constraint deixa de considerar o horário ocupado.
        $this->appointment($this->bruno, '10:00');
        $this->assertSame(2, Appointment::withoutGlobalScopes()->count());
    }

    public function test_the_customer_is_emailed_when_the_business_cancels()
    {
        Notification::fake();
        $appointment = $this->appointment($this->bruno, '10:00');

        $this->actingAs($this->owner)->patch($this->url($appointment), ['status' => 'cancelled']);

        Notification::assertSentOnDemand(AppointmentCancelled::class, fn (AppointmentCancelled $notification) => ! $notification->byCustomer);
    }

    public function test_internal_status_changes_send_no_email()
    {
        Notification::fake();
        $appointment = $this->appointment($this->bruno, '10:00', AppointmentStatus::Pending);

        $this->actingAs($this->owner)->patch($this->url($appointment), ['status' => 'confirmed']);

        Notification::assertNothingSent();
    }

    public function test_final_statuses_cannot_change()
    {
        $appointment = $this->appointment($this->bruno, '07:00', AppointmentStatus::Completed);

        $this->actingAs($this->owner)
            ->patch($this->url($appointment), ['status' => 'cancelled'])
            ->assertSessionHasErrors(['status' => 'Um agendamento "Concluído" não pode passar para "Cancelado".']);
    }

    public function test_staff_manages_only_their_own_appointments()
    {
        $own = $this->appointment($this->rafael, '10:00', AppointmentStatus::Pending);
        $others = $this->appointment($this->bruno, '10:00', AppointmentStatus::Pending);

        $this->actingAs($this->staff)->patch($this->url($own), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patch($this->url($others), ['status' => 'confirmed'])->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $own->fresh()->status);
        $this->assertSame(AppointmentStatus::Pending, $others->fresh()->status);
    }

    public function test_appointments_of_another_business_are_not_found()
    {
        $alheio = Appointment::factory()->create();

        $this->actingAs($this->owner)->patch($this->url($alheio), ['status' => 'cancelled'])->assertNotFound();
    }

    public function test_unknown_statuses_are_rejected()
    {
        $appointment = $this->appointment($this->bruno, '10:00');

        $this->actingAs($this->owner)
            ->patch($this->url($appointment), ['status' => 'archived'])
            ->assertSessionHasErrors('status');
    }

    private function url(Appointment $appointment): string
    {
        return "/painel/agendamentos/{$appointment->id}/status";
    }
}
