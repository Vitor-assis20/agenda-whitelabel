<?php

namespace Tests\Feature\Panel;

use App\Models\Professional;
use App\Models\Service;
use Inertia\Testing\AssertableInertia as Assert;

class ProfessionalsTest extends PanelTestCase
{
    public function test_lists_professionals_with_services_and_working_hours()
    {
        $corte = Service::factory()->for($this->tenant)->create();
        $this->bruno->services()->attach($corte);
        $this->tenant->run(fn () => $this->bruno->workingHours()->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']));

        $this->actingAs($this->owner)
            ->get('/painel/profissionais')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/professionals')
                ->has('professionals', 2)
                ->where('professionals.0.name', 'Bruno')
                ->where('professionals.0.service_ids', [$corte->id])
                ->where('professionals.0.working_hours', [['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']])
                ->has('services', 1)
            );
    }

    public function test_creates_a_professional_with_services()
    {
        $services = Service::factory()->count(2)->for($this->tenant)->create();

        $this->actingAs($this->owner)
            ->post('/painel/profissionais', $this->payload(['service_ids' => $services->pluck('id')->all()]))
            ->assertSessionHasNoErrors();

        $carla = Professional::withoutGlobalScopes()->where('name', 'Carla')->sole();
        $this->assertSame($this->tenant->id, $carla->tenant_id);
        $this->assertDatabaseCount('professional_service', 2);
        $this->assertDatabaseHas('professional_service', ['professional_id' => $carla->id, 'tenant_id' => $this->tenant->id]);
    }

    public function test_cannot_link_services_of_another_business()
    {
        $alheio = Service::factory()->create();

        $this->actingAs($this->owner)
            ->post('/painel/profissionais', $this->payload(['service_ids' => [$alheio->id]]))
            ->assertSessionHasErrors('service_ids.0');
    }

    public function test_updating_syncs_the_services()
    {
        [$corte, $barba] = Service::factory()->count(2)->for($this->tenant)->create();
        $this->bruno->services()->attach($corte);

        $this->actingAs($this->owner)
            ->put("/painel/profissionais/{$this->bruno->id}", $this->payload(['name' => 'Bruno', 'service_ids' => [$barba->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$barba->id], $this->tenant->run(fn () => $this->bruno->services()->pluck('services.id')->all()));
    }

    public function test_deletes_a_professional_without_history()
    {
        $this->actingAs($this->owner)->delete("/painel/profissionais/{$this->bruno->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($this->bruno);
    }

    public function test_a_professional_with_history_can_only_be_deactivated()
    {
        $this->appointment($this->bruno, '10:00');

        $this->actingAs($this->owner)
            ->delete("/painel/profissionais/{$this->bruno->id}")
            ->assertSessionHasErrors('professional');

        $this->assertModelExists($this->bruno);
    }

    public function test_staff_cannot_manage_professionals()
    {
        $this->actingAs($this->staff)->get('/painel/profissionais')->assertForbidden();
        $this->actingAs($this->staff)->put("/painel/profissionais/{$this->rafael->id}", $this->payload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Carla',
            'email' => 'carla@example.com',
            'bio' => null,
            'is_active' => true,
            'service_ids' => [],
            ...$overrides,
        ];
    }
}
