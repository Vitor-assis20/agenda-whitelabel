<?php

use App\Http\Controllers\Panel\AgendaController;
use App\Http\Controllers\Panel\AppointmentStatusController;
use App\Http\Controllers\Panel\BrandingController;
use App\Http\Controllers\Panel\ProfessionalController;
use App\Http\Controllers\Panel\ServiceController;
use App\Http\Controllers\Panel\SwitchTenantController;
use App\Http\Controllers\Panel\WorkingHoursController;
use Illuminate\Support\Facades\Route;

// Painel do negócio, no domínio principal.
Route::middleware(['auth', 'verified', 'panel'])
    ->prefix('painel')
    ->name('panel.')
    ->group(function () {
        Route::get('agenda', AgendaController::class)->name('agenda');

        Route::patch('agendamentos/{appointment}/status', [AppointmentStatusController::class, 'update'])
            ->name('appointments.status');

        Route::post('negocio', SwitchTenantController::class)->name('switch-tenant');

        Route::middleware('can:manage-tenant')->group(function () {
            Route::resource('servicos', ServiceController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['servicos' => 'service'])
                ->names('services');

            Route::resource('profissionais', ProfessionalController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['profissionais' => 'professional'])
                ->names('professionals');

            Route::put('profissionais/{professional}/expediente', [WorkingHoursController::class, 'update'])
                ->name('professionals.working-hours');

            Route::get('marca', [BrandingController::class, 'edit'])->name('branding.edit');
            // POST: o upload do logo vai como multipart/form-data.
            Route::post('marca', [BrandingController::class, 'update'])->name('branding.update');
        });
    });
