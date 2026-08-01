<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

final class RestoreVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $backupPath): array
    {
        $backupPath = rtrim($backupPath, '/');
        $manifestPath = $backupPath.'/manifest.json';
        $mediaTarget = storage_path('framework/gss-restore-verify/'.basename($backupPath));
        $database = 'gss_restore_verify_'.now()->format('YmdHis').'_'.bin2hex(random_bytes(3));

        if (! is_dir($backupPath)) {
            throw new RuntimeException('Backup path does not exist or is not a directory.');
        }

        if (! is_file($manifestPath)) {
            throw new RuntimeException('Backup manifest is missing.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $this->validateChecksums($backupPath, $manifest['checksums'] ?? []);
        $connection = PostgresConnection::configured();

        try {
            $this->runDatabaseCommand('createdb', $database, $connection);
            $this->restoreDatabase(
                $database,
                $backupPath.'/'.($manifest['database_dump'] ?? 'database.sql'),
                $connection,
            );

            File::deleteDirectory($mediaTarget);
            File::copyDirectory($backupPath.'/'.($manifest['media_directory'] ?? 'media'), $mediaTarget);

            return [
                'status' => 'passed',
                'database' => $database,
                'student_count' => $this->count($database, 'students', $connection),
                'ceremony_count' => $this->count($database, 'ceremonies', $connection),
                'media_files' => is_dir($mediaTarget) ? count(File::allFiles($mediaTarget)) : 0,
            ];
        } finally {
            try {
                $this->runDatabaseCommand('dropdb', $database, $connection, throw: false);
            } finally {
                File::deleteDirectory($mediaTarget);
            }
        }
    }

    /**
     * @param  array<string, string>  $checksums
     */
    private function validateChecksums(string $backupPath, array $checksums): void
    {
        if ($checksums === []) {
            throw new RuntimeException('Backup manifest does not include checksums.');
        }

        foreach ($checksums as $relative => $expected) {
            if (str_starts_with($relative, '/') || str_contains($relative, '..')) {
                throw new RuntimeException('Backup manifest contains an unsafe path.');
            }

            $file = $backupPath.'/'.$relative;

            if (! is_file($file)) {
                throw new RuntimeException("Backup file is missing: {$relative}");
            }

            if (! hash_equals($expected, hash_file('sha256', $file))) {
                throw new RuntimeException("Backup checksum mismatch: {$relative}");
            }
        }
    }

    private function restoreDatabase(
        string $database,
        string $dumpPath,
        PostgresConnection $connection,
    ): void {
        if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
            throw new RuntimeException('Database dump is missing or empty.');
        }

        $dumpPath = realpath($dumpPath);

        if ($dumpPath === false) {
            throw new RuntimeException('Database dump path could not be resolved.');
        }

        $process = new Process(array_merge(
            ['psql'],
            $connection->arguments($database),
            ['--set=ON_ERROR_STOP=1', '--file='.$dumpPath],
        ), base_path(), $connection->environment());
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Disposable database restore failed.');
        }
    }

    private function count(
        string $database,
        string $table,
        PostgresConnection $connection,
    ): int {
        if (! in_array($table, ['students', 'ceremonies'], true)) {
            throw new RuntimeException('Unsupported disposable restore table.');
        }

        $process = new Process(array_merge(
            ['psql'],
            $connection->arguments($database),
            ['--tuples-only', '--no-align', '--command=select count(*) from '.$table],
        ), base_path(), $connection->environment());
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Could not count {$table} in disposable restore database.");
        }

        return (int) trim($process->getOutput());
    }

    private function runDatabaseCommand(
        string $command,
        string $database,
        PostgresConnection $connection,
        bool $throw = true,
    ): void {
        $arguments = array_merge(
            [$command],
            $connection->arguments(),
            ['--maintenance-db='.$connection->database],
        );

        if ($command === 'dropdb') {
            $arguments[] = '--if-exists';
        }

        $arguments[] = $database;
        $process = new Process($arguments, base_path(), $connection->environment());
        $process->setTimeout(60);
        $process->run();

        if ($throw && ! $process->isSuccessful()) {
            throw new RuntimeException('Disposable restore database command failed.');
        }
    }
}
