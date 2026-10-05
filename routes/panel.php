<?php

use App\Http\Controllers\Panel\AgendaController;
use App\Http\Controllers\Panel\AppointmentStatusController;
use App\Http\Controllers\Panel\SwitchTenantController;
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
    });
