<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/booking.php';

// Plataforma (site, login e painel) só no domínio principal; os
// subdomínios ficam exclusivos para a página pública de cada tenant.
Route::domain(config('app.domain'))->group(function () {
    Route::get('/', function () {
        return Inertia::render('welcome');
    })->name('home');

    // Destino padrão após o login.
    Route::middleware(['auth'])->get('dashboard', fn () => to_route('panel.agenda'))->name('dashboard');

    require __DIR__.'/panel.php';
    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
