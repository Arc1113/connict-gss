<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AnnouncementBatchController;
use App\Http\Controllers\Admin\AnnouncementCandidateAudioController;
use App\Http\Controllers\Admin\AnnouncementCandidateReviewController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CeremonyAnnouncementSettingsController;
use App\Http\Controllers\Admin\CeremonyController;
use App\Http\Controllers\Admin\CeremonyOperationalResetController;
use App\Http\Controllers\Admin\CeremonySummaryController;
use App\Http\Controllers\Admin\DeviceConnectionController;
use App\Http\Controllers\Admin\PronunciationRuleController;
use App\Http\Controllers\Admin\ScanLogController;
use App\Http\Controllers\Admin\StageDesignTemplateController;
use App\Http\Controllers\Admin\StageEventController;
use App\Http\Controllers\Admin\StudentAnnouncementController;
use App\Http\Controllers\Admin\StudentAwardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentQrCodeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoiceComparisonAudioController;
use App\Http\Controllers\Admin\VoiceComparisonController;
use App\Http\Controllers\Admin\VoiceGenerationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CompleteCurrentPresentationController;
use App\Http\Controllers\LocalCertificateController;
use App\Http\Controllers\OnboardingProgressController;
use App\Http\Controllers\PhoneOnboardingController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScannerLookupController;
use App\Http\Controllers\StageControlController;
use App\Http\Controllers\StageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/connect-phone', PhoneOnboardingController::class)
    ->name('phone-onboarding');
Route::get('/connect-phone/local-ca.crt', LocalCertificateController::class)
    ->name('phone-onboarding.certificate');

Route::get('/', function (Request $request) {
    if ($request->user() !== null) {
        return redirect($request->user()->role->homePath());
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::put('/onboarding/{tourKey}', [OnboardingProgressController::class, 'update'])
        ->where('tourKey', '[a-z0-9-]+')
        ->name('onboarding.update');
    Route::post('/onboarding/{tourKey}/reset', [OnboardingProgressController::class, 'reset'])
        ->where('tourKey', '[a-z0-9-]+')
        ->name('onboarding.reset');
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/scanner', ScannerController::class)
        ->middleware('can:operate-scanner')
        ->name('scanner');
    Route::post('/scanner/lookup', ScannerLookupController::class)
        ->middleware('can:operate-scanner')
        ->name('scanner.lookup');
    Route::post('/scans', [ScanController::class, 'store'])
        ->name('scans.store');
    Route::post('/scans/manual', [ScanController::class, 'manual'])
        ->name('scans.manual');

    Route::get('/stage', [StageController::class, 'show'])
        ->middleware('can:operate-stage')
        ->name('stage');
    Route::get('/stage/state', [StageController::class, 'state'])
        ->middleware('can:operate-stage')
        ->name('stage.state');
    Route::post('/stage/audio-status', [StageController::class, 'audioStatus'])
        ->middleware('can:operate-stage')
        ->name('stage.audio-status');
    Route::post('/stage/complete-current', CompleteCurrentPresentationController::class)
        ->middleware('can:operate-stage')
        ->name('stage.complete-current');
    Route::get('/stage-control', [StageControlController::class, 'show'])
        ->middleware('can:operate-stage')
        ->name('stage-control');
    Route::middleware('can:operate-stage')->group(function (): void {
        Route::get('/stage/queue', [StageControlController::class, 'queue'])
            ->name('stage.queue');
        Route::post('/stage/present-next', [StageControlController::class, 'presentNext'])
            ->name('stage.present-next');
        Route::post('/stage/complete-and-present-next', [StageControlController::class, 'completeAndPresentNext'])
            ->name('stage.complete-and-present-next');
        Route::post('/stage/return-current', [StageControlController::class, 'returnCurrent'])
            ->name('stage.return-current');
        Route::post('/stage/present/{queueItem}', [StageControlController::class, 'presentSelected'])
            ->whereNumber('queueItem')
            ->name('stage.present-selected');
        Route::post('/stage/skip/{queueItem}', [StageControlController::class, 'skip'])
            ->whereNumber('queueItem')
            ->name('stage.skip');
        Route::post('/stage/return/{queueItem}', [StageControlController::class, 'returnToQueue'])
            ->whereNumber('queueItem')
            ->name('stage.return');
        Route::post('/stage/replay', [StageControlController::class, 'replay'])
            ->name('stage.replay');
        Route::post('/stage/holding/activate', [StageControlController::class, 'activateHolding'])
            ->name('stage.holding.activate');
        Route::post('/stage/holding/deactivate', [StageControlController::class, 'deactivateHolding'])
            ->name('stage.holding.deactivate');
        Route::post('/stage/automatic/pause', [StageControlController::class, 'pauseAutomatic'])
            ->name('stage.automatic.pause');
        Route::post('/stage/automatic/resume', [StageControlController::class, 'resumeAutomatic'])
            ->name('stage.automatic.resume');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('index');
        Route::get('/voice-generation', VoiceGenerationController::class)
            ->name('voice-generation');
        Route::get('/pronunciation-rules', [PronunciationRuleController::class, 'index'])
            ->name('pronunciation-rules.index');
        Route::post('/pronunciation-rules', [PronunciationRuleController::class, 'store'])
            ->name('pronunciation-rules.store');
        Route::put('/pronunciation-rules/{pronunciationRule}', [PronunciationRuleController::class, 'update'])
            ->name('pronunciation-rules.update');
        Route::post('/pronunciation-rules/preview', [PronunciationRuleController::class, 'preview'])
            ->name('pronunciation-rules.preview');
        Route::get('/device-connections', DeviceConnectionController::class)
            ->middleware('can:manage-device-connections')
            ->name('device-connections');
        Route::resource('users', UserController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->middleware('can:manage-users');
        Route::get('/scan-logs', [ScanLogController::class, 'index'])->name('scan-logs.index');
        Route::get('/scan-logs/export', [ScanLogController::class, 'export'])->name('scan-logs.export');
        Route::get('/stage-events', [StageEventController::class, 'index'])->name('stage-events.index');
        Route::get('/stage-events/export', [StageEventController::class, 'export'])->name('stage-events.export');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('/reports/ceremony-summary', [CeremonySummaryController::class, 'index'])->name('reports.ceremony-summary');
        Route::get('/reports/ceremony-summary/export', [CeremonySummaryController::class, 'export'])->name('reports.ceremony-summary.export');
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::post('/backups/{backup}/verify', [BackupController::class, 'verify'])->name('backups.verify');

        Route::resource('ceremonies', CeremonyController::class);
        Route::resource('stage-designs', StageDesignTemplateController::class)
            ->parameters(['stage-designs' => 'stageDesignTemplate'])
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get(
            '/ceremonies/{ceremony}/announcement-settings',
            [CeremonyAnnouncementSettingsController::class, 'show'],
        )->name('ceremonies.announcement-settings.show');
        Route::post(
            '/ceremonies/{ceremony}/announcement-settings',
            [CeremonyAnnouncementSettingsController::class, 'store'],
        )->name('ceremonies.announcement-settings.store');
        Route::post(
            '/ceremonies/{ceremony}/announcement-settings/preview',
            [CeremonyAnnouncementSettingsController::class, 'preview'],
        )->name('ceremonies.announcement-settings.preview');
        Route::post(
            '/ceremonies/{ceremony}/announcement-settings/voice-comparison',
            [VoiceComparisonController::class, 'store'],
        )->name('ceremonies.announcement-settings.voice-comparison.store');
        Route::get(
            '/ceremonies/{ceremony}/announcement-settings/voice-comparison/{sample}',
            VoiceComparisonAudioController::class,
        )->name('ceremonies.announcement-settings.voice-comparison.show');
        Route::get(
            '/ceremonies/{ceremony}/announcement-batches',
            [AnnouncementBatchController::class, 'index'],
        )->name('ceremonies.announcement-batches.index');
        Route::post(
            '/ceremonies/{ceremony}/announcement-batches',
            [AnnouncementBatchController::class, 'store'],
        )->name('ceremonies.announcement-batches.store');
        Route::post(
            '/ceremonies/{ceremony}/announcement-batches/{batch}/cancel',
            [AnnouncementBatchController::class, 'cancel'],
        )->name('ceremonies.announcement-batches.cancel');
        Route::post(
            '/ceremonies/{ceremony}/announcement-batches/{batch}/resume',
            [AnnouncementBatchController::class, 'resume'],
        )->name('ceremonies.announcement-batches.resume');
        Route::post(
            '/ceremonies/{ceremony}/operational-reset',
            CeremonyOperationalResetController::class,
        )->name('ceremonies.operational-reset');

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
                Route::post(
                    '/{studentImport}/media-package',
                    [StudentImportController::class, 'replaceMediaPackage'],
                )->name('media-package.replace');
                Route::post(
                    '/{studentImport}/csv',
                    [StudentImportController::class, 'replaceCsv'],
                )->name('csv.replace');
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

        Route::get(
            '/students/qr-codes',
            [StudentQrCodeController::class, 'index'],
        )->name('students.qr-codes.index');
        Route::post(
            '/students/qr-codes/batch',
            [StudentQrCodeController::class, 'batch'],
        )->name('students.qr-codes.batch');
        Route::get(
            '/students/{student}/qr-code',
            [StudentQrCodeController::class, 'show'],
        )->name('students.qr-codes.show');
        Route::get(
            '/students/{student}/qr-code/image',
            [StudentQrCodeController::class, 'image'],
        )->name('students.qr-codes.image');
        Route::get(
            '/students/{student}/qr-code/print',
            [StudentQrCodeController::class, 'print'],
        )->name('students.qr-codes.print');
        Route::post(
            '/students/{student}/qr-code/regenerate',
            [StudentQrCodeController::class, 'regenerate'],
        )->name('students.qr-codes.regenerate');

        Route::delete(
            '/students/{student}/photo',
            [StudentController::class, 'destroyPhoto'],
        )->name('students.photo.destroy');
        Route::delete(
            '/students/{student}/audio',
            [StudentController::class, 'destroyAudio'],
        )->name('students.audio.destroy');
        Route::post(
            '/students/{student}/audio/needs-review',
            [StudentController::class, 'markAudioNeedsReview'],
        )->name('students.audio.needs-review');
        Route::post(
            '/students/{student}/audio/approve',
            [StudentController::class, 'approveAudio'],
        )->name('students.audio.approve');
        Route::post(
            '/students/{student}/audio/reject',
            [StudentController::class, 'rejectAudio'],
        )->name('students.audio.reject');
        Route::post(
            '/students/{student}/awards',
            [StudentAwardController::class, 'store'],
        )->name('students.awards.store');
        Route::put(
            '/students/{student}/awards/{award}',
            [StudentAwardController::class, 'update'],
        )->name('students.awards.update');
        Route::delete(
            '/students/{student}/awards/{award}',
            [StudentAwardController::class, 'destroy'],
        )->name('students.awards.destroy');
        Route::post(
            '/students/{student}/awards/{award}/move',
            [StudentAwardController::class, 'move'],
        )->name('students.awards.move');
        Route::get(
            '/students/{student}/announcement-preview',
            [StudentAnnouncementController::class, 'preview'],
        )->name('students.announcement-preview');
        Route::post(
            '/students/{student}/announcement-candidates',
            [StudentAnnouncementController::class, 'generate'],
        )->name('students.announcement-candidates.store');
        Route::get(
            '/students/{student}/announcement-candidates/{generation}',
            AnnouncementCandidateAudioController::class,
        )->name('students.announcement-candidates.show');
        Route::post(
            '/students/{student}/announcement-candidates/{generation}/approve',
            [AnnouncementCandidateReviewController::class, 'approve'],
        )->name('students.announcement-candidates.approve');
        Route::post(
            '/students/{student}/announcement-candidates/{generation}/reject',
            [AnnouncementCandidateReviewController::class, 'reject'],
        )->name('students.announcement-candidates.reject');
        Route::resource('students', StudentController::class);
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
