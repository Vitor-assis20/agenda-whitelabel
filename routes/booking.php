<?php

use App\Http\Controllers\Booking\BookingController;
use Illuminate\Support\Facades\Route;

// Página pública de cada negócio, em {slug}.APP_DOMAIN.
Route::domain('{tenant}.'.config('app.domain'))
    ->middleware('tenant')
    ->name('booking.')
    ->group(function () {
        Route::get('/', [BookingController::class, 'show'])->name('show');

        Route::get('horarios', [BookingController::class, 'slots'])
            ->middleware('throttle:60,1')
            ->name('slots');

        Route::post('agendar', [BookingController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');

        // Assinadas: o id sequencial sozinho não dá acesso ao agendamento.
        // O link chega ao cliente na confirmação e nos e-mails.
        Route::get('agendamentos/{appointment}', [BookingController::class, 'confirmation'])
            ->middleware('signed')
            ->name('confirmation');

        Route::post('agendamentos/{appointment}/cancelar', [BookingController::class, 'cancel'])
            ->middleware(['signed', 'throttle:10,1'])
            ->name('cancel');
    });
