<?php

namespace Tests\Feature\Phase3;

use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;
use Tests\TestCase;

class ConcurrencyWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_valid_scans_cannot_create_two_presentations(): void
    {
        $this->requireProcessSupport();
        $ceremony = $this->activeCeremony();
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();
        $first = $this->eligibleStudent($ceremony, 'CONCURRENT-FIRST');
        $second = $this->eligibleStudent($ceremony, 'CONCURRENT-SECOND');

        $results = $this->runConcurrently([
            [
                'operation' => 'scan',
                'arguments' => [$first->qr_token, (string) $scanner->id],
            ],
            [
                'operation' => 'scan',
                'arguments' => [$second->qr_token, (string) $scanner->id],
            ],
        ]);

        $statuses = collect($results)->pluck('status')->sort()->values()->all();

        $this->assertSame(['accepted', 'stage_busy'], $statuses);
        $this->assertSame(1, StageQueueItem::query()
            ->where('status', StageQueueStatus::Presenting)
            ->count());
        $this->assertSame(1, StageQueueItem::query()->count());
        $this->assertDatabaseCount('scan_logs', 2);
        $this->assertDatabaseCount('stage_events', 1);

        $state = StageState::query()->whereBelongsTo($ceremony)->sole();

        $this->assertNotNull($state->current_queue_item_id);
        $this->assertSame(1, $state->version);
    }

    public function test_completion_racing_a_scan_leaves_a_consistent_stage(): void
    {
        $this->requireProcessSupport();
        $ceremony = $this->activeCeremony();
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();
        $currentStudent = $this->eligibleStudent($ceremony, 'CURRENT-STUDENT');
        $nextStudent = $this->eligibleStudent($ceremony, 'NEXT-STUDENT');
        $currentItem = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $currentStudent->id,
            'status' => StageQueueStatus::Presenting,
            'presented_at' => now(),
        ]);
        StageState::query()
            ->whereBelongsTo($ceremony)
            ->update([
                'current_queue_item_id' => $currentItem->id,
                'display_mode' => 'graduate',
                'version' => 1,
            ]);

        $results = $this->runConcurrently([
            [
                'operation' => 'complete',
                'arguments' => [(string) $stageOperator->id],
            ],
            [
                'operation' => 'scan',
                'arguments' => [$nextStudent->qr_token, (string) $scanner->id],
            ],
        ]);

        $this->assertSame('completed', $results[0]['status']);
        $this->assertContains($results[1]['status'], ['accepted', 'stage_busy']);
        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $currentItem->id,
            'status' => StageQueueStatus::Completed->value,
        ]);
        $this->assertLessThanOrEqual(
            1,
            StageQueueItem::query()
                ->where('status', StageQueueStatus::Presenting)
                ->count(),
        );

        $state = StageState::query()->whereBelongsTo($ceremony)->sole();
        $presenting = StageQueueItem::query()
            ->where('status', StageQueueStatus::Presenting)
            ->first();

        if ($results[1]['status'] === 'accepted') {
            $this->assertNotNull($presenting);
            $this->assertSame($presenting->id, $state->current_queue_item_id);
            $this->assertSame(3, $state->version);
        } else {
            $this->assertNull($presenting);
            $this->assertNull($state->current_queue_item_id);
            $this->assertSame(2, $state->version);
        }
    }

    private function activeCeremony(): Ceremony
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        StageState::factory()->create([
            'ceremony_id' => $ceremony->id,
            'version' => 0,
        ]);

        return $ceremony;
    }

    private function eligibleStudent(Ceremony $ceremony, string $token): Student
    {
        return Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'qr_token' => $token,
        ]);
    }

    private function requireProcessSupport(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('The proc_open function is required for concurrency tests.');
        }
    }

    /**
     * @param  list<array{operation: string, arguments: list<string>}>  $operations
     * @return list<array<string, mixed>>
     */
    private function runConcurrently(array $operations): array
    {
        $directory = sys_get_temp_dir().'/gss-concurrency-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create concurrency test directory.');
        }

        $startFile = $directory.'/start';
        $processes = [];
        $environment = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => config('database.default'),
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'),
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ]);

        try {
            foreach ($operations as $index => $operation) {
                $resultFile = $directory.'/result-'.$index.'.json';
                $stderrFile = $directory.'/stderr-'.$index.'.txt';
                $command = [
                    PHP_BINARY,
                    base_path('tests/Support/concurrent-worker.php'),
                    $operation['operation'],
                    $startFile,
                    $resultFile,
                    ...$operation['arguments'],
                ];
                $process = proc_open(
                    $command,
                    [
                        0 => ['file', '/dev/null', 'r'],
                        1 => ['file', '/dev/null', 'w'],
                        2 => ['file', $stderrFile, 'w'],
                    ],
                    $pipes,
                    base_path(),
                    $environment,
                );

                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start a concurrency test process.');
                }

                $processes[$index] = [
                    'process' => $process,
                    'result' => $resultFile,
                    'stderr' => $stderrFile,
                ];
            }

            file_put_contents($startFile, 'go');

            foreach ($processes as $processData) {
                $exitCode = proc_close($processData['process']);

                if ($exitCode !== 0 && ! file_exists($processData['result'])) {
                    $message = file_exists($processData['stderr'])
                        ? file_get_contents($processData['stderr'])
                        : 'Unknown child-process failure.';

                    throw new RuntimeException(
                        trim((string) $message) !== ''
                            ? (string) $message
                            : 'Child process exited without a result.',
                    );
                }
            }

            return array_map(
                fn (int $index): array => json_decode(
                    file_get_contents($processes[$index]['result']),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
                array_keys($operations),
            );
        } finally {
            foreach ($processes as $processData) {
                if (! is_resource($processData['process'])) {
                    continue;
                }

                $status = proc_get_status($processData['process']);

                if ($status['running']) {
                    proc_terminate($processData['process']);
                }

                proc_close($processData['process']);
            }

            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }
}
