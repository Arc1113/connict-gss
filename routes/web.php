<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
        'phase' => 'Phase 1',
        'stack' => [
            'Laravel',
            'React',
            'Inertia.js',
            'Vite',
            'PostgreSQL',
        ],
    ]);
})->name('home');
