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

        try {
            $this->runDatabaseCommand("createdb -U \"\$POSTGRES_USER\" {$database}");
            $this->restoreDatabase($database, $backupPath.'/'.($manifest['database_dump'] ?? 'database.sql'));

            File::deleteDirectory($mediaTarget);
            File::copyDirectory($backupPath.'/'.($manifest['media_directory'] ?? 'media'), $mediaTarget);

            return [
                'status' => 'passed',
                'database' => $database,
                'student_count' => $this->count($database, 'students'),
                'ceremony_count' => $this->count($database, 'ceremonies'),
                'media_files' => is_dir($mediaTarget) ? count(File::allFiles($mediaTarget)) : 0,
            ];
        } finally {
            try {
                $this->runDatabaseCommand("dropdb -U \"\$POSTGRES_USER\" --if-exists {$database}", throw: false);
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

    private function restoreDatabase(string $database, string $dumpPath): void
    {
        if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
            throw new RuntimeException('Database dump is missing or empty.');
        }

        $process = new Process([
            'docker',
            'compose',
            'exec',
            '-T',
            'database',
            'sh',
            '-lc',
            "psql -v ON_ERROR_STOP=1 -U \"\$POSTGRES_USER\" -d {$database}",
        ], base_path());
        $process->setInput(file_get_contents($dumpPath));
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Disposable database restore failed.');
        }
    }

    private function count(string $database, string $table): int
    {
        $process = new Process([
            'docker',
            'compose',
            'exec',
            '-T',
            'database',
            'sh',
            '-lc',
            "psql -tAc \"select count(*) from {$table}\" -U \"\$POSTGRES_USER\" -d {$database}",
        ], base_path());
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Could not count {$table} in disposable restore database.");
        }

        return (int) trim($process->getOutput());
    }

    private function runDatabaseCommand(string $command, bool $throw = true): void
    {
        $process = new Process([
            'docker',
            'compose',
            'exec',
            '-T',
            'database',
            'sh',
            '-lc',
            $command,
        ], base_path());
        $process->setTimeout(60);
        $process->run();

        if ($throw && ! $process->isSuccessful()) {
            throw new RuntimeException('Disposable restore database command failed.');
        }
    }
}
