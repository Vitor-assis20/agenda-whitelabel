<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus;
use App\Enums\TenantRole;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class AgendaTest extends PanelTestCase
{
    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/painel/agenda')->assertRedirect('/login');
    }

    public function test_dashboard_leads_to_the_agenda()
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertRedirect('/painel/agenda');
    }

    public function test_users_without_a_business_see_a_friendly_page()
    {
        $this->actingAs(User::factory()->create())
            ->get('/painel/agenda')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('panel/no-business'));
    }

    public function test_owner_sees_the_appointments_of_the_day_in_local_time()
    {
        $this->appointment($this->rafael, '10:00');
        $this->appointment($this->bruno, '09:00');
        $this->appointment($this->bruno, '14:00');
        $this->appointment($this->bruno, '09:00', date: '2026-11-11');
        Professional::factory()->create(); // outro tenant

        $this->actingAs($this->owner)
            ->get('/painel/agenda?date='.self::DATE)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/agenda')
                ->where('date.label', 'Terça-feira, 10 de novembro')
                ->where('date.previous', '2026-11-09')
                ->where('date.next', '2026-11-11')
                ->where('date.is_today', true)
                ->has('professionals', 2)
                ->where('professionals.0.name', 'Bruno')
                ->has('professionals.0.appointments', 2)
                ->where('professionals.0.appointments.0.starts_at', '09:00')
                ->where('professionals.0.appointments.0.ends_at', '10:00')
                ->where('professionals.0.appointments.1.starts_at', '14:00')
                ->where('professionals.1.name', 'Rafael')
                ->has('professionals.1.appointments', 1)
            );
    }

    public function test_summary_ignores_cancellations_and_no_shows_in_the_revenue()
    {
        $this->travelTo($this->localTime('20:00'));
        $this->appointment($this->bruno, '09:00', AppointmentStatus::Completed, priceCents: 5000);
        $this->appointment($this->bruno, '10:00', AppointmentStatus::Confirmed, priceCents: 7500);
        $this->appointment($this->bruno, '11:00', AppointmentStatus::Cancelled, priceCents: 9000);
        $this->appointment($this->bruno, '12:00', AppointmentStatus::NoShow, priceCents: 9000);

        $this->actingAs($this->owner)
            ->get('/painel/agenda?date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.appointments', 2)
                ->where('summary.completed', 1)
                ->where('summary.expected_revenue_cents', 12500)
            );
    }

    public function test_staff_only_sees_their_own_agenda()
    {
        $this->appointment($this->rafael, '10:00');
        $this->appointment($this->bruno, '09:00');

        $this->actingAs($this->staff)
            ->get('/painel/agenda?date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->has('professionals', 1)
                ->where('professionals.0.name', 'Rafael')
                ->where('panel.current.role', 'staff')
            );
    }

    public function test_actions_depend_on_status_and_on_whether_the_appointment_started()
    {
        $this->appointment($this->bruno, '07:00', AppointmentStatus::Confirmed);
        $this->appointment($this->bruno, '09:00', AppointmentStatus::Confirmed);
        $this->appointment($this->bruno, '10:00', AppointmentStatus::Pending);
        $this->appointment($this->bruno, '11:00', AppointmentStatus::Cancelled);

        $this->actingAs($this->owner)
            ->get('/painel/agenda?date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->where('professionals.0.appointments.0.actions', ['completed', 'no_show', 'cancelled'])
                ->where('professionals.0.appointments.1.actions', ['cancelled'])
                ->where('professionals.0.appointments.2.actions', ['confirmed', 'cancelled'])
                ->where('professionals.0.appointments.3.actions', [])
            );
    }

    public function test_invalid_dates_are_rejected()
    {
        $this->actingAs($this->owner)
            ->get('/painel/agenda?date=10-11-2026')
            ->assertSessionHasErrors('date');
    }

    public function test_owner_can_switch_between_their_businesses()
    {
        $studio = Tenant::factory()->withUser($this->owner, TenantRole::Admin)->create(['name' => 'Studio']);

        $this->actingAs($this->owner)
            ->get('/painel/agenda')
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.current.name', 'Barbearia')
                ->has('panel.tenants', 2)
            );

        $this->post('/painel/negocio', ['tenant_id' => $studio->id])->assertRedirect('/painel/agenda');

        $this->get('/painel/agenda')
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.current.name', 'Studio')
                ->where('panel.current.role', 'admin')
                ->has('professionals', 0)
            );
    }

    public function test_cannot_switch_to_a_business_the_user_does_not_belong_to()
    {
        $alheio = Tenant::factory()->create();

        $this->actingAs($this->owner)
            ->post('/painel/negocio', ['tenant_id' => $alheio->id])
            ->assertSessionHasErrors('tenant_id');

        $this->get('/painel/agenda')->assertInertia(fn (Assert $page) => $page->where('panel.current.name', 'Barbearia'));
    }

    public function test_panel_exposes_the_public_page_of_the_business()
    {
        $this->actingAs($this->owner)
            ->get('/painel/agenda')
            // A porta acompanha o APP_URL do ambiente.
            ->assertInertia(fn (Assert $page) => $page->where(
                'panel.current.public_url',
                fn (string $url) => preg_match("#^http://{$this->tenant->slug}\.localhost(:\d+)?$#", $url) === 1,
            ));
    }
}
