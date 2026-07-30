<?php

use App\Support\BackupManager;
use App\Support\RestoreVerifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gss:backup {--path= : Local destination outside public/}', function () {
    $record = app(BackupManager::class)->create(
        destination: $this->option('path'),
    );

    if ($record->status !== 'completed') {
        $this->error($record->failure_summary ?? 'Backup failed safely.');

        return Command::FAILURE;
    }

    $this->info("Backup completed: {$record->identifier}");
    $this->line("Destination: {$record->path_label}");
    $this->line("Manifest checksum: {$record->checksum}");

    return Command::SUCCESS;
})->purpose('Create a local PostgreSQL and media backup with manifest checksums');

Artisan::command('gss:restore-verify {--backup= : Backup directory to verify}', function () {
    $backup = $this->option('backup');

    if (! is_string($backup) || $backup === '') {
        $this->error('Provide --backup=/path/to/gss-backup-YYYYMMDD-HHMMSS');

        return Command::FAILURE;
    }

    try {
        $result = app(RestoreVerifier::class)->verify($backup);
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $this->info('Disposable restore verification passed.');
    $this->line("Ceremonies: {$result['ceremony_count']}");
    $this->line("Graduates: {$result['student_count']}");
    $this->line("Media files: {$result['media_files']}");

    return Command::SUCCESS;
})->purpose('Verify a backup by restoring it into a disposable PostgreSQL database');
