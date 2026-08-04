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

            $announcement = $this->verifyAnnouncementSnapshot($database, $mediaTarget, $connection);

            return [
                'status' => 'passed',
                'database' => $database,
                'student_count' => $this->count($database, 'students', $connection),
                'ceremony_count' => $this->count($database, 'ceremonies', $connection),
                'media_files' => is_dir($mediaTarget) ? count(File::allFiles($mediaTarget)) : 0,
                'announcement' => $announcement,
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

    /**
     * @return array<string, int>
     */
    private function verifyAnnouncementSnapshot(
        string $database,
        string $mediaTarget,
        PostgresConnection $connection,
    ): array {
        $counts = [];

        foreach ([
            'awards' => 'student_awards',
            'templates' => 'announcement_templates',
            'generations' => 'announcement_generations',
            'batches' => 'announcement_generation_batches',
            'batch_items' => 'announcement_generation_batch_items',
        ] as $label => $table) {
            $counts[$label] = $this->countAnnouncementTable($database, $table, $connection);
        }

        $invalidHashes = $this->scalar($database, <<<'SQL'
SELECT
    (SELECT count(*) FROM announcement_generations WHERE source_hash !~ '^[0-9a-f]{64}$')
    +
    (SELECT count(*) FROM students WHERE audio_source_hash IS NOT NULL AND audio_source_hash !~ '^[0-9a-f]{64}$')
SQL, $connection);

        if ($invalidHashes !== 0) {
            throw new RuntimeException('Restored announcement source hashes are invalid.');
        }

        $invalidActiveReferences = $this->scalar($database, <<<'SQL'
SELECT count(*)
FROM students AS students
LEFT JOIN announcement_generations AS generations
    ON generations.id = students.audio_generation_id
    AND generations.student_id = students.id
    AND generations.ceremony_id = students.ceremony_id
WHERE students.audio_source_type = 'azure_tts'
  AND (
      students.audio_generation_id IS NULL
      OR students.audio_source_hash IS NULL
      OR students.audio_path IS NULL
      OR generations.id IS NULL
      OR generations.status <> 'approved'
      OR generations.source_hash <> students.audio_source_hash
      OR generations.approved_audio_path <> students.audio_path
  )
SQL, $connection);

        if ($invalidActiveReferences !== 0) {
            throw new RuntimeException('Restored active generated-audio references are inconsistent.');
        }

        $candidatePaths = $this->column(
            $database,
            'SELECT candidate_audio_path FROM announcement_generations WHERE candidate_audio_path IS NOT NULL ORDER BY id',
            $connection,
        );

        foreach ($candidatePaths as $path) {
            if (! preg_match('#^announcement-candidates/[A-Za-z0-9_./-]+\.mp3$#', $path)
                || str_contains($path, '..')) {
                throw new RuntimeException('Restored announcement candidate policy contains an unsafe path.');
            }

            $relative = substr($path, strlen('announcement-candidates/'));
            $this->requireReadableMedia(
                $mediaTarget.'/private-announcement-candidates/'.$relative,
                'private announcement candidate',
            );
        }

        $candidateRoot = $mediaTarget.'/private-announcement-candidates';
        $restoredCandidateCount = is_dir($candidateRoot)
            ? count(File::allFiles($candidateRoot))
            : 0;

        if ($restoredCandidateCount !== count($candidatePaths)) {
            throw new RuntimeException(
                'Restored private candidate media does not match generation metadata.',
            );
        }

        $activePaths = $this->column(
            $database,
            "SELECT audio_path FROM students WHERE audio_source_type = 'azure_tts' ORDER BY id",
            $connection,
        );

        foreach ($activePaths as $path) {
            if (! str_starts_with($path, 'storage/graduates/audio/')) {
                throw new RuntimeException('Restored generated active audio has an unsafe path.');
            }

            $relative = substr($path, strlen('storage/graduates/'));
            $this->requireReadableMedia(
                $mediaTarget.'/storage-graduates/'.$relative,
                'approved generated audio',
            );
        }

        $counts['private_candidates'] = count($candidatePaths);
        $counts['active_generated_audio'] = count($activePaths);

        return $counts;
    }

    private function countAnnouncementTable(
        string $database,
        string $table,
        PostgresConnection $connection,
    ): int {
        if (! in_array($table, [
            'student_awards',
            'announcement_templates',
            'announcement_generations',
            'announcement_generation_batches',
            'announcement_generation_batch_items',
        ], true)) {
            throw new RuntimeException('Unsupported announcement restore table.');
        }

        return $this->scalar($database, 'SELECT count(*) FROM '.$table, $connection);
    }

    private function scalar(
        string $database,
        string $sql,
        PostgresConnection $connection,
    ): int {
        $values = $this->column($database, $sql, $connection);

        if (count($values) !== 1 || preg_match('/^\d+$/', $values[0]) !== 1) {
            throw new RuntimeException('Disposable announcement restore query returned an invalid result.');
        }

        return (int) $values[0];
    }

    /** @return list<string> */
    private function column(
        string $database,
        string $sql,
        PostgresConnection $connection,
    ): array {
        $process = new Process(array_merge(
            ['psql'],
            $connection->arguments($database),
            ['--tuples-only', '--no-align', '--set=ON_ERROR_STOP=1', '--command='.$sql],
        ), base_path(), $connection->environment());
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Could not validate the restored announcement snapshot.');
        }

        $output = trim($process->getOutput());

        return $output === '' ? [] : array_values(array_filter(
            preg_split('/\R/', $output) ?: [],
            static fn (string $value): bool => $value !== '',
        ));
    }

    private function requireReadableMedia(string $path, string $label): void
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) === 0) {
            throw new RuntimeException("Restored {$label} is missing or unreadable.");
        }
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
