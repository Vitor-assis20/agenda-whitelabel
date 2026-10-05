<?php

namespace Tests\Feature\Tenancy;

use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\MissingTenantException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_only_see_records_of_the_current_tenant()
    {
        $barbearia = Tenant::factory()->create();
        $clinica = Tenant::factory()->create();
        Professional::factory()->count(2)->for($barbearia)->create();
        Professional::factory()->count(3)->for($clinica)->create();

        $this->assertSame(2, $barbearia->run(fn () => Professional::count()));
        $this->assertSame(3, $clinica->run(fn () => Professional::count()));
    }

    public function test_records_of_another_tenant_cannot_be_found_by_id()
    {
        $barbearia = Tenant::factory()->create();
        $alheio = Professional::factory()->create();

        $this->assertNull($barbearia->run(fn () => Professional::find($alheio->id)));
    }

    public function test_queries_return_nothing_without_a_tenant_in_context()
    {
        Professional::factory()->count(2)->create();

        $this->assertSame(0, Professional::count());
    }

    public function test_tenant_id_is_filled_from_the_current_tenant()
    {
        $tenant = Tenant::factory()->create();

        $service = $tenant->run(fn () => Service::create([
            'name' => 'Corte',
            'duration_minutes' => 30,
            'price_cents' => 5000,
        ]));

        $this->assertSame($tenant->id, $service->tenant_id);
    }

    public function test_creating_without_a_tenant_in_context_fails()
    {
        $this->expectException(MissingTenantException::class);

        Service::create(['name' => 'Corte', 'duration_minutes' => 30, 'price_cents' => 5000]);
    }

    public function test_run_restores_the_previous_tenant()
    {
        [$externo, $interno] = Tenant::factory()->count(2)->create();
        $current = app(CurrentTenant::class);

        $externo->run(function () use ($interno, $externo, $current) {
            $interno->run(fn () => $this->assertTrue($current->get()->is($interno)));

            $this->assertTrue($current->get()->is($externo));
        });

        $this->assertFalse($current->check());
    }

    public function test_tenant_relations_work_outside_a_tenant_context()
    {
        $tenant = Tenant::factory()->create();
        Professional::factory()->count(2)->for($tenant)->create();

        $this->assertCount(2, $tenant->professionals);
    }

    public function test_professional_services_pivot_carries_the_tenant()
    {
        $tenant = Tenant::factory()->create();
        $professional = Professional::factory()->for($tenant)->create();
        $service = Service::factory()->for($tenant)->create();

        $professional->services()->attach($service);

        $this->assertDatabaseHas('professional_service', [
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
        ]);
        $this->assertTrue($tenant->run(fn () => $professional->services()->first()->is($service)));
    }

    public function test_professionals_can_be_eager_loaded_through_services()
    {
        $tenant = Tenant::factory()->create();
        $service = Service::factory()->for($tenant)->create();
        $service->professionals()->attach(Professional::factory()->count(2)->for($tenant)->create());

        $loaded = $tenant->run(fn () => Service::with('professionals')->sole());

        $this->assertCount(2, $loaded->professionals);
    }
}
