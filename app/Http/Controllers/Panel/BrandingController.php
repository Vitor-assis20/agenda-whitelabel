<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\BrandingRequest;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function edit(): Response
    {
        $tenant = $this->current->get();

        return Inertia::render('panel/branding', [
            'business' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $tenant->publicBranding(),
            ],
        ]);
    }

    public function update(BrandingRequest $request): RedirectResponse
    {
        $tenant = $this->current->get();
        $branding = [...$tenant->branding, 'primary_color' => strtolower($request->validated('primary_color'))];

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            $this->deleteLogo($tenant);
            $branding['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            // Nome aleatório: trocar o logo muda a URL e invalida o cache do navegador.
            $branding['logo_path'] = $request->file('logo')->store("tenants/{$tenant->id}", Tenant::ASSETS_DISK);
        }

        $tenant->update(['name' => $request->validated('name'), 'branding' => $branding]);

        return back();
    }

    private function deleteLogo(Tenant $tenant): void
    {
        if ($path = $tenant->branding['logo_path'] ?? null) {
            Storage::disk(Tenant::ASSETS_DISK)->delete($path);
        }
    }
}
