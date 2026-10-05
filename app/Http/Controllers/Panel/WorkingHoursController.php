<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\WorkingHoursRequest;
use App\Models\Professional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class WorkingHoursController extends Controller
{
    /**
     * Substitui o expediente semanal inteiro do profissional.
     *
     * Agendamentos já feitos fora do novo expediente são mantidos: a mudança
     * só afeta os horários oferecidos daqui em diante.
     */
    public function update(WorkingHoursRequest $request, Professional $professional): RedirectResponse
    {
        DB::transaction(function () use ($request, $professional) {
            $professional->workingHours()->delete();
            $professional->workingHours()->createMany($request->validated('hours'));
        });

        return back();
    }
}
