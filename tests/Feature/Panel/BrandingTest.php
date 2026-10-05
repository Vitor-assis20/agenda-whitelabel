<?php

namespace Tests\Feature\Panel;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class BrandingTest extends PanelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Tenant::ASSETS_DISK);
    }

    public function test_shows_the_current_branding()
    {
        $this->tenant->update(['branding' => ['primary_color' => '#b45309', 'logo_path' => null]]);

        $this->actingAs($this->owner)
            ->get('/painel/marca')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/branding')
                ->where('business.name', 'Barbearia')
                ->where('business.branding', ['primary_color' => '#b45309', 'logo_url' => null])
            );
    }

    public function test_updates_the_name_and_normalizes_the_color()
    {
        $this->actingAs($this->owner)
            ->post('/painel/marca', ['name' => 'Barbearia do Zé', 'primary_color' => '#1D4ED8'])
            ->assertSessionHasNoErrors();

        $this->tenant->refresh();
        $this->assertSame('Barbearia do Zé', $this->tenant->name);
        $this->assertSame('#1d4ed8', $this->tenant->branding['primary_color']);
    }

    public function test_rejects_invalid_colors()
    {
        $this->actingAs($this->owner)
            ->post('/painel/marca', ['name' => 'Barbearia', 'primary_color' => 'red; background: url(x)'])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_uploads_a_logo_shown_on_the_public_page()
    {
        $this->actingAs($this->owner)
            ->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 200, 200)]))
            ->assertSessionHasNoErrors();

        $path = $this->tenant->refresh()->branding['logo_path'];
        $this->assertStringStartsWith("tenants/{$this->tenant->id}/", $path);
        Storage::disk(Tenant::ASSETS_DISK)->assertExists($path);

        $this->get("http://{$this->tenant->slug}.localhost/")
            ->assertInertia(fn (Assert $page) => $page->where('tenant.branding.logo_url', Storage::disk(Tenant::ASSETS_DISK)->url($path)));
    }

    public function test_replacing_the_logo_deletes_the_previous_file()
    {
        $this->actingAs($this->owner)->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
        $old = $this->tenant->refresh()->branding['logo_path'];

        $this->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('b.png', 100, 100)]));

        Storage::disk(Tenant::ASSETS_DISK)->assertMissing($old);
        Storage::disk(Tenant::ASSETS_DISK)->assertExists($this->tenant->refresh()->branding['logo_path']);
    }

    public function test_removes_the_logo()
    {
        $this->actingAs($this->owner)->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
        $old = $this->tenant->refresh()->branding['logo_path'];

        $this->post('/painel/marca', $this->payload(['remove_logo' => true]))->assertSessionHasNoErrors();

        Storage::disk(Tenant::ASSETS_DISK)->assertMissing($old);
        $this->assertNull($this->tenant->refresh()->branding['logo_path']);
    }

    public function test_saving_without_a_new_logo_keeps_the_current_one()
    {
        $this->actingAs($this->owner)->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
        $path = $this->tenant->refresh()->branding['logo_path'];

        $this->post('/painel/marca', $this->payload(['name' => 'Outro nome']));

        $this->assertSame($path, $this->tenant->refresh()->branding['logo_path']);
        Storage::disk(Tenant::ASSETS_DISK)->assertExists($path);
    }

    public function test_rejects_svg_large_and_tiny_images()
    {
        $this->actingAs($this->owner);

        $this->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')]))
            ->assertSessionHasErrors('logo');
        $this->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('big.png', 500, 500)->size(2048)]))
            ->assertSessionHasErrors('logo');
        $this->post('/painel/marca', $this->payload(['logo' => UploadedFile::fake()->image('tiny.png', 32, 32)]))
            ->assertSessionHasErrors('logo');

        $this->assertSame([], Storage::disk(Tenant::ASSETS_DISK)->allFiles());
    }

    public function test_staff_cannot_change_the_branding()
    {
        $this->actingAs($this->staff)->get('/painel/marca')->assertForbidden();
        $this->actingAs($this->staff)->post('/painel/marca', $this->payload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['name' => 'Barbearia', 'primary_color' => '#b45309', ...$overrides];
    }
}
