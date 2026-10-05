<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Antes das rotas sem domínio: elas também casariam com os subdomínios.
require __DIR__.'/booking.php';

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
