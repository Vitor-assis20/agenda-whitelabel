<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        $services = Service::query()
            ->withCount(['professionals' => fn ($query) => $query->where('is_active', true)])
            ->withExists('appointments')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('panel/services', [
            'services' => $services->map(fn (Service $service) => [
                ...$service->only('id', 'name', 'description', 'duration_minutes', 'price_cents', 'is_active', 'professionals_count'),
                // Com histórico, o serviço só pode ser desativado.
                'deletable' => ! $service->appointments_exists,
            ]),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create($request->validated());

        return back();
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return back();
    }

    public function destroy(Service $service): RedirectResponse
    {
        if ($service->appointments()->exists()) {
            throw ValidationException::withMessages([
                'service' => 'Este serviço tem agendamentos no histórico. Desative-o em vez de excluir.',
            ]);
        }

        $service->delete();

        return back();
    }
}
