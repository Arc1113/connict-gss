<?php

namespace App\Support;

use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementGenerationBatchItem;
use App\Models\AnnouncementTemplate;
use App\Models\BackupRecord;
use App\Models\Ceremony;
use App\Models\StudentAward;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class BackupManager
{
    public function create(?User $actor = null, ?string $destination = null): BackupRecord
    {
        $destination = $this->destination($destination);
        $this->ensureSafeDestination($destination);
        File::ensureDirectoryExists($destination);

        $identifier = 'gss-backup-'.now()->format('Ymd-His');
        $workspace = $destination.'/'.$identifier;
        $record = BackupRecord::query()->create([
            'identifier' => $identifier,
            'path_label' => basename($destination).'/'.$identifier,
            'status' => 'running',
            'started_at' => now()->utc(),
            'created_by' => $actor?->id,
            'verification_status' => 'pending',
        ]);

        try {
            $this->ensureAnnouncementPreparationIsQuiescent();
            $this->ensureCandidateRetentionPolicy();
            File::ensureDirectoryExists($workspace);
            $dump = $workspace.'/database.sql';
            $this->databaseDump($dump);

            $media = $workspace.'/media';
            File::ensureDirectoryExists($media);
            $this->copyMedia($media);

            $checksums = $this->checksums($workspace);
            file_put_contents(
                $workspace.'/SHA256SUMS',
                collect($checksums)
                    ->map(fn (string $hash, string $file): string => "{$hash}  {$file}")
                    ->implode(PHP_EOL).PHP_EOL,
            );

            $manifest = [
                'identifier' => $identifier,
                'created_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'git_commit' => $this->gitCommit(),
                'application_version' => config('connict.version'),
                'source_commit' => config('connict.commit'),
                'laravel_version' => app()->version(),
                'postgresql_version' => $this->postgresVersion(),
                'database_dump' => 'database.sql',
                'media_directory' => 'media',
                'checksums' => $checksums,
                'ceremony_summary' => [
                    'ceremonies' => Ceremony::query()->count(),
                    'active_ceremonies' => Ceremony::query()->where('status', 'active')->count(),
                ],
                'announcement_summary' => [
                    'awards' => StudentAward::query()->count(),
                    'templates' => AnnouncementTemplate::query()->count(),
                    'generations' => AnnouncementGeneration::query()->count(),
                    'batches' => AnnouncementGenerationBatch::query()->count(),
                    'batch_items' => AnnouncementGenerationBatchItem::query()->count(),
                ],
                'snapshot_rule' => 'Preparation mutations and the tts-preparation worker must be stopped for the backup window.',
                'candidate_retention' => 'Referenced private candidates are retained under protected backup media.',
                'status' => 'completed',
                'verification_status' => 'passed',
                'restore_instructions' => 'docs/backup-and-restore.md',
            ];
            file_put_contents(
                $workspace.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            );

            $record->fill([
                'status' => 'completed',
                'completed_at' => now()->utc(),
                'file_size' => $this->directorySize($workspace),
                'checksum' => hash_file('sha256', $workspace.'/manifest.json'),
                'verification_status' => 'passed',
            ])->save();
        } catch (Throwable $exception) {
            File::deleteDirectory($workspace);
            $record->fill([
                'status' => 'failed',
                'completed_at' => now()->utc(),
                'verification_status' => 'failed',
                'failure_summary' => str($exception->getMessage())->limit(500)->toString(),
            ])->save();
        }

        return $record->refresh();
    }

    public function destination(?string $destination = null): string
    {
        $destination ??= env('GSS_BACKUP_PATH');
        $destination ??= env('GSS_DATA_DIR')
            ? rtrim((string) env('GSS_DATA_DIR'), '/\\').DIRECTORY_SEPARATOR.'backups'
            : storage_path('app/gss-backups');

        return rtrim($destination, '/');
    }

    public function fullPath(BackupRecord $record, ?string $destination = null): string
    {
        return $this->destination($destination).'/'.$record->identifier;
    }

    public function ensureSafeDestination(string $destination): void
    {
        $public = realpath(public_path()) ?: public_path();
        $resolvedParent = realpath(dirname($destination)) ?: dirname($destination);
        $candidate = $resolvedParent.'/'.basename($destination);

        if (str_starts_with($candidate, $public)) {
            throw new RuntimeException('Backup destination must not be inside the public directory.');
        }
    }

    private function databaseDump(string $path): void
    {
        $directory = realpath(dirname($path));

        if ($directory === false) {
            throw new RuntimeException('PostgreSQL dump destination could not be resolved.');
        }

        $path = $directory.'/'.basename($path);
        $connection = PostgresConnection::configured();
        $process = new Process(array_merge([
            $this->postgresExecutable('pg_dump'),
        ], $connection->arguments($connection->database), [
            '--file='.$path,
            '--format=plain',
            '--no-owner',
            '--no-privileges',
            '--exclude-table-data=announcement_voice_samples',
        ]), base_path(), $connection->environment());
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            logger()->error('PostgreSQL backup process failed.', [
                'exit_code' => $process->getExitCode(),
                'error' => str($process->getErrorOutput())->squish()->limit(500)->toString(),
            ]);

            throw new RuntimeException('PostgreSQL dump failed. Confirm the database is reachable and its backup credentials are valid.');
        }

        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('PostgreSQL dump was empty.');
        }
    }

    private function ensureAnnouncementPreparationIsQuiescent(): void
    {
        $generating = AnnouncementGeneration::query()
            ->where('status', 'generating')
            ->exists();
        $processing = AnnouncementGenerationBatchItem::query()
            ->where('status', 'processing')
            ->exists();

        if ($generating || $processing) {
            throw new RuntimeException(
                'Backup requires a quiet preparation window. Wait for the active synthesis request to finish and stop the TTS worker.',
            );
        }
    }

    private function ensureCandidateRetentionPolicy(): void
    {
        $directory = trim((string) config(
            'announcements.azure.candidate_directory',
            'announcement-candidates',
        ), '/');
        $root = Storage::disk((string) config(
            'announcements.azure.candidate_disk',
            'local',
        ))->path($directory);
        $prefix = $directory.'/';
        $expected = AnnouncementGeneration::query()
            ->whereNotNull('candidate_audio_path')
            ->pluck('candidate_audio_path')
            ->map(function (string $path) use ($prefix): string {
                if (! str_starts_with($path, $prefix) || str_contains($path, '..')) {
                    throw new RuntimeException('Announcement candidate retention contains an unsafe path.');
                }

                return substr($path, strlen($prefix));
            })
            ->sort()
            ->values()
            ->all();
        $actual = is_dir($root)
            ? collect(File::allFiles($root))
                ->map(fn ($file): string => $file->getRelativePathname())
                ->sort()
                ->values()
                ->all()
            : [];

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Private candidate storage does not match generation metadata. Reconcile missing or orphan files before backup.',
            );
        }
    }

    private function postgresVersion(): ?string
    {
        $process = new Process([$this->postgresExecutable('psql'), '--version'], base_path());
        $process->setTimeout(20);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    private function postgresExecutable(string $name): string
    {
        $directory = trim((string) env('GSS_POSTGRES_BIN'));

        if ($directory === '') {
            return $name;
        }

        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';

        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.$suffix;
    }

    private function copyMedia(string $target): void
    {
        $sources = [
            storage_path('app/public/graduates') => 'storage-graduates',
            public_path('media/phase3') => 'development-phase3-media',
        ];
        $candidateDirectory = trim((string) config(
            'announcements.azure.candidate_directory',
            'announcement-candidates',
        ), '/');

        if ($candidateDirectory !== '' && ! str_contains($candidateDirectory, '..')) {
            $sources[Storage::disk((string) config(
                'announcements.azure.candidate_disk',
                'local',
            ))->path($candidateDirectory)] = 'private-announcement-candidates';
        }

        foreach ($sources as $source => $label) {
            if (! is_dir($source)) {
                continue;
            }

            $this->copyDirectory($source, $target.'/'.$label);
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        File::ensureDirectoryExists($target);

        foreach (File::allFiles($source) as $file) {
            $relative = $file->getRelativePathname();

            if (str_contains($relative, '..')) {
                throw new RuntimeException('Unsafe media path detected.');
            }

            File::ensureDirectoryExists(dirname($target.'/'.$relative));
            File::copy($file->getPathname(), $target.'/'.$relative);
        }
    }

    /**
     * @return array<string, string>
     */
    private function checksums(string $workspace): array
    {
        $checksums = [];

        foreach (File::allFiles($workspace) as $file) {
            $relative = str($file->getPathname())->after($workspace.'/')->toString();

            if ($relative === 'manifest.json' || $relative === 'SHA256SUMS') {
                continue;
            }

            $checksums[$relative] = hash_file('sha256', $file->getPathname());
        }

        ksort($checksums);

        return $checksums;
    }

    private function directorySize(string $path): int
    {
        return collect(File::allFiles($path))
            ->sum(fn ($file): int => $file->getSize());
    }

    private function gitCommit(): ?string
    {
        $process = new Process(['git', 'rev-parse', '--short=12', 'HEAD'], base_path());
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
}
