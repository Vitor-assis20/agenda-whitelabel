<?php

namespace Tests\Feature\Panel;

use App\Models\Service;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

class ServicesTest extends PanelTestCase
{
    public function test_lists_the_services_of_the_current_business()
    {
        $corte = Service::factory()->for($this->tenant)->create(['name' => 'Corte']);
        $this->bruno->services()->attach($corte);
        Service::factory()->create(); // outro tenant

        $this->actingAs($this->owner)
            ->get('/painel/servicos')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/services')
                ->has('services', 1)
                ->where('services.0.name', 'Corte')
                ->where('services.0.professionals_count', 1)
                ->where('services.0.deletable', true)
            );
    }

    public function test_creates_a_service_in_the_current_business()
    {
        $this->actingAs($this->owner)
            ->post('/painel/servicos', $this->payload())
            ->assertSessionHasNoErrors();

        $service = Service::withoutGlobalScopes()->sole();
        $this->assertSame($this->tenant->id, $service->tenant_id);
        $this->assertSame(5000, $service->price_cents);
    }

    public function test_validates_duration_and_price()
    {
        $this->actingAs($this->owner)
            ->post('/painel/servicos', $this->payload(['duration_minutes' => 32, 'price_cents' => -1]))
            ->assertSessionHasErrors(['duration_minutes', 'price_cents']);
    }

    public function test_names_are_unique_per_business_only()
    {
        Service::factory()->for($this->tenant)->create(['name' => 'Corte']);
        Service::factory()->for(Tenant::factory())->create(['name' => 'Barba']);

        $this->actingAs($this->owner)->post('/painel/servicos', $this->payload(['name' => 'Corte']))
            ->assertSessionHasErrors(['name' => 'Já existe um serviço com este nome.']);
        $this->post('/painel/servicos', $this->payload(['name' => 'Barba']))->assertSessionHasNoErrors();
    }

    public function test_updates_and_deactivates_a_service_keeping_its_own_name()
    {
        $service = Service::factory()->for($this->tenant)->create(['name' => 'Corte']);

        $this->actingAs($this->owner)
            ->put("/painel/servicos/{$service->id}", $this->payload(['name' => 'Corte', 'price_cents' => 6000, 'is_active' => false]))
            ->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame(6000, $service->price_cents);
        $this->assertFalse($service->is_active);
    }

    public function test_deletes_a_service_without_history()
    {
        $service = Service::factory()->for($this->tenant)->create();

        $this->actingAs($this->owner)->delete("/painel/servicos/{$service->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($service);
    }

    public function test_a_service_with_history_can_only_be_deactivated()
    {
        $appointment = $this->appointment($this->bruno, '10:00');

        $this->actingAs($this->owner)
            ->delete("/painel/servicos/{$appointment->service_id}")
            ->assertSessionHasErrors('service');

        $this->assertDatabaseHas('services', ['id' => $appointment->service_id]);
    }

    public function test_services_of_another_business_are_not_found()
    {
        $alheio = Service::factory()->create();

        $this->actingAs($this->owner)->put("/painel/servicos/{$alheio->id}", $this->payload())->assertNotFound();
        $this->actingAs($this->owner)->delete("/painel/servicos/{$alheio->id}")->assertNotFound();
    }

    public function test_staff_cannot_manage_services()
    {
        $this->actingAs($this->staff)->get('/painel/servicos')->assertForbidden();
        $this->actingAs($this->staff)->post('/painel/servicos', $this->payload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Corte',
            'description' => 'Corte na tesoura',
            'duration_minutes' => 30,
            'price_cents' => 5000,
            'is_active' => true,
            ...$overrides,
        ];
    }
}
