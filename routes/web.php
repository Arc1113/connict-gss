<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CeremonyController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CompleteCurrentPresentationController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\StageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
        'phase' => 'Phase 5',
        'stack' => [
            'Laravel',
            'React',
            'Inertia.js',
            'Vite',
            'PostgreSQL',
        ],
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/scanner', ScannerController::class)
        ->middleware('can:operate-scanner')
        ->name('scanner');
    Route::post('/scans', [ScanController::class, 'store'])
        ->name('scans.store');

    Route::get('/stage', [StageController::class, 'show'])
        ->middleware('can:operate-stage')
        ->name('stage');
    Route::get('/stage/state', [StageController::class, 'state'])
        ->middleware('can:operate-stage')
        ->name('stage.state');
    Route::post('/stage/complete-current', CompleteCurrentPresentationController::class)
        ->middleware('can:operate-stage')
        ->name('stage.complete-current');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('index');
        Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');

        Route::resource('ceremonies', CeremonyController::class);

        Route::prefix('students/import')
            ->name('students.import.')
            ->group(function (): void {
                Route::get('/', [StudentImportController::class, 'index'])
                    ->name('index');
                Route::get('/template', [StudentImportController::class, 'template'])
                    ->name('template');
                Route::post('/inspect', [StudentImportController::class, 'inspect'])
                    ->name('inspect');
                Route::get(
                    '/{studentImport}/mapping',
                    [StudentImportController::class, 'mapping'],
                )->name('mapping');
                Route::post(
                    '/{studentImport}/preview',
                    [StudentImportController::class, 'storeMapping'],
                )->name('preview.store');
                Route::get(
                    '/{studentImport}/preview',
                    [StudentImportController::class, 'preview'],
                )->name('preview');
                Route::post(
                    '/{studentImport}/confirm',
                    [StudentImportController::class, 'confirm'],
                )->name('confirm');
                Route::get(
                    '/{studentImport}/summary',
                    [StudentImportController::class, 'summary'],
                )->name('summary');
            });

        Route::delete(
            '/students/{student}/photo',
            [StudentController::class, 'destroyPhoto'],
        )->name('students.photo.destroy');
        Route::delete(
            '/students/{student}/audio',
            [StudentController::class, 'destroyAudio'],
        )->name('students.audio.destroy');
        Route::resource('students', StudentController::class);
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
