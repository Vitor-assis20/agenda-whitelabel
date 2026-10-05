<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ProfessionalRequest;
use App\Models\Professional;
use App\Models\Service;
use App\Models\WorkingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalController extends Controller
{
    public function index(): Response
    {
        $professionals = Professional::query()
            ->with([
                'services:id',
                'workingHours' => fn ($query) => $query->orderBy('weekday')->orderBy('starts_at'),
            ])
            ->withExists('appointments')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('panel/professionals', [
            'professionals' => $professionals->map(fn (Professional $professional) => [
                ...$professional->only('id', 'name', 'email', 'bio', 'is_active'),
                'service_ids' => $professional->services->pluck('id'),
                'working_hours' => $professional->workingHours->map(fn (WorkingHour $hours) => [
                    'weekday' => $hours->weekday,
                    'starts_at' => substr($hours->starts_at, 0, 5),
                    'ends_at' => substr($hours->ends_at, 0, 5),
                ]),
                'deletable' => ! $professional->appointments_exists,
            ]),
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(ProfessionalRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $professional = Professional::create($request->safe()->except('service_ids'));
            $professional->services()->sync($request->validated('service_ids', []));
        });

        return back();
    }

    public function update(ProfessionalRequest $request, Professional $professional): RedirectResponse
    {
        DB::transaction(function () use ($request, $professional) {
            $professional->update($request->safe()->except('service_ids'));
            $professional->services()->sync($request->validated('service_ids', []));
        });

        return back();
    }

    public function destroy(Professional $professional): RedirectResponse
    {
        if ($professional->appointments()->exists()) {
            throw ValidationException::withMessages([
                'professional' => 'Este profissional tem agendamentos no histórico. Desative-o em vez de excluir.',
            ]);
        }

        $professional->delete();

        return back();
    }
}
