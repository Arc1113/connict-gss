<?php

namespace Tests\Feature\Phase7;

use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
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

class StageQueueConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_duplicate_concurrent_scans_create_one_active_item(): void
    {
        $this->requireProcessSupport();
        [$ceremony, $scanner, $operator] = $this->operationContext();
        $student = $this->student($ceremony, 'DUPLICATE-RACE');

        $results = $this->runConcurrently([
            [
                'operation' => 'scan',
                'arguments' => [$student->qr_token, (string) $scanner->id],
            ],
            [
                'operation' => 'scan',
                'arguments' => [$student->qr_token, (string) $scanner->id],
            ],
        ]);

        $this->assertSame(
            ['already_queued', 'queued'],
            collect($results)->pluck('status')->sort()->values()->all(),
        );
        $this->assertDatabaseCount('stage_queue_items', 1);
        $this->assertDatabaseCount('scan_logs', 2);
        $this->assertDatabaseCount('stage_events', 1);
        $this->assertNotNull($operator);
    }

    public function test_two_present_next_commands_can_present_only_one_graduate(): void
    {
        $this->requireProcessSupport();
        [$ceremony, , $operator] = $this->operationContext();
        $this->queue($this->student($ceremony, 'NEXT-ONE'), 1);
        $this->queue($this->student($ceremony, 'NEXT-TWO'), 2);

        $results = $this->runConcurrently([
            [
                'operation' => 'present_next',
                'arguments' => [(string) $operator->id],
            ],
            [
                'operation' => 'present_next',
                'arguments' => [(string) $operator->id],
            ],
        ]);

        $this->assertSame(
            ['presented', 'stage_busy'],
            collect($results)->pluck('status')->sort()->values()->all(),
        );
        $this->assertSame(1, StageQueueItem::query()
            ->where('status', StageQueueStatus::Presenting)
            ->count());
        $this->assertSame(1, StageQueueItem::query()
            ->where('status', StageQueueStatus::Waiting)
            ->count());
    }

    public function test_present_next_and_selected_race_leave_one_presenting_item(): void
    {
        $this->requireProcessSupport();
        [$ceremony, , $operator] = $this->operationContext();
        $first = $this->queue($this->student($ceremony, 'FIRST'), 1);
        $selected = $this->queue($this->student($ceremony, 'SELECTED'), 2);

        $results = $this->runConcurrently([
            [
                'operation' => 'present_next',
                'arguments' => [(string) $operator->id],
            ],
            [
                'operation' => 'present_selected',
                'arguments' => [
                    (string) $selected->id,
                    (string) $operator->id,
                ],
            ],
        ]);

        $this->assertContains('presented', array_column($results, 'status'));
        $this->assertContains('stage_busy', array_column($results, 'status'));
        $this->assertSame(1, StageQueueItem::query()
            ->where('status', StageQueueStatus::Presenting)
            ->count());
        $this->assertContains(
            StageQueueItem::query()
                ->where('status', StageQueueStatus::Presenting)
                ->value('id'),
            [$first->id, $selected->id],
        );
    }

    public function test_skip_and_present_selected_race_preserves_one_legal_terminal_state(): void
    {
        $this->requireProcessSupport();
        [$ceremony, , $operator] = $this->operationContext();
        $item = $this->queue($this->student($ceremony, 'SKIP-PRESENT'), 1);

        $results = $this->runConcurrently([
            [
                'operation' => 'skip',
                'arguments' => [
                    (string) $item->id,
                    (string) $operator->id,
                ],
            ],
            [
                'operation' => 'present_selected',
                'arguments' => [
                    (string) $item->id,
                    (string) $operator->id,
                ],
            ],
        ]);

        $statuses = array_column($results, 'status');
        $this->assertContains('queue_item_unavailable', $statuses);
        $this->assertTrue(
            in_array('skipped', $statuses, true)
            || in_array('presented', $statuses, true),
        );
        $this->assertContains(
            $item->refresh()->status,
            [StageQueueStatus::Skipped, StageQueueStatus::Presenting],
        );
    }

    public function test_duplicate_return_race_appends_the_item_once(): void
    {
        $this->requireProcessSupport();
        [$ceremony, , $operator] = $this->operationContext();
        $item = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $this->student($ceremony, 'RETURN')->id,
            'status' => StageQueueStatus::Skipped,
            'queue_position' => null,
        ]);

        $results = $this->runConcurrently([
            [
                'operation' => 'return',
                'arguments' => [
                    (string) $item->id,
                    (string) $operator->id,
                ],
            ],
            [
                'operation' => 'return',
                'arguments' => [
                    (string) $item->id,
                    (string) $operator->id,
                ],
            ],
        ]);

        $this->assertSame(
            ['queue_item_unavailable', 'returned'],
            collect($results)->pluck('status')->sort()->values()->all(),
        );
        $this->assertSame(StageQueueStatus::Waiting, $item->refresh()->status);
        $this->assertSame(1, $item->queue_position);
    }

    public function test_complete_and_replay_race_keeps_completion_authoritative(): void
    {
        $this->requireProcessSupport();
        [$ceremony, , $operator] = $this->operationContext();
        $item = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $this->student($ceremony, 'REPLAY-RACE', [
                'audio_path' => 'media/phase3/audio/student-01.wav',
            ])->id,
            'status' => StageQueueStatus::Presenting,
            'queue_position' => 1,
            'presented_at' => now(),
        ]);
        StageState::query()->whereBelongsTo($ceremony)->update([
            'current_queue_item_id' => $item->id,
            'display_mode' => 'graduate',
            'version' => 1,
        ]);

        $results = $this->runConcurrently([
            [
                'operation' => 'complete',
                'arguments' => [(string) $operator->id],
            ],
            [
                'operation' => 'replay',
                'arguments' => [(string) $operator->id],
            ],
        ]);

        $this->assertSame('completed', $results[0]['status']);
        $this->assertContains($results[1]['status'], ['replayed', 'stage_idle']);
        $this->assertSame(StageQueueStatus::Completed, $item->refresh()->status);
        $this->assertNull(
            StageState::query()->whereBelongsTo($ceremony)->value(
                'current_queue_item_id',
            ),
        );
    }

    public function test_pause_and_automatic_scan_race_never_preempts_or_loses_pause(): void
    {
        $this->requireProcessSupport();
        [$ceremony, $scanner, $operator] = $this->operationContext(
            PresentationMode::Automatic,
        );
        $student = $this->student($ceremony, 'AUTO-PAUSE-RACE');

        $results = $this->runConcurrently([
            [
                'operation' => 'pause_automatic',
                'arguments' => [(string) $operator->id],
            ],
            [
                'operation' => 'scan',
                'arguments' => [$student->qr_token, (string) $scanner->id],
            ],
        ]);

        $this->assertSame('automatic_paused', $results[0]['status']);
        $this->assertSame('queued', $results[1]['status']);
        $state = StageState::query()->whereBelongsTo($ceremony)->sole();
        $this->assertTrue($state->automatic_paused);
        $this->assertContains(
            StageQueueItem::query()->sole()->status,
            [StageQueueStatus::Waiting, StageQueueStatus::Presenting],
        );
        $this->assertLessThanOrEqual(
            1,
            StageQueueItem::query()
                ->where('status', StageQueueStatus::Presenting)
                ->count(),
        );
    }

    /**
     * @return array{Ceremony, User, User}
     */
    private function operationContext(
        PresentationMode $mode = PresentationMode::Controlled,
    ): array {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'presentation_mode' => $mode,
            'activated_at' => now(),
        ]);
        StageState::factory()->create([
            'ceremony_id' => $ceremony->id,
            'version' => 0,
        ]);

        return [
            $ceremony,
            User::factory()->role(UserRole::ScannerOperator)->create(),
            User::factory()->role(UserRole::StageOperator)->create(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function student(
        Ceremony $ceremony,
        string $token,
        array $attributes = [],
    ): Student {
        return Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'qr_token' => $token,
            ...$attributes,
        ]);
    }

    private function queue(Student $student, int $position): StageQueueItem
    {
        return StageQueueItem::factory()->create([
            'ceremony_id' => $student->ceremony_id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => $position,
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
        $directory = sys_get_temp_dir().'/gss-phase7-'.bin2hex(random_bytes(8));

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
                $process = proc_open(
                    [
                        PHP_BINARY,
                        base_path('tests/Support/concurrent-worker.php'),
                        $operation['operation'],
                        $startFile,
                        $resultFile,
                        ...$operation['arguments'],
                    ],
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

                    throw new RuntimeException(trim((string) $message));
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
                if (is_resource($processData['process'])) {
                    $status = proc_get_status($processData['process']);

                    if ($status['running']) {
                        proc_terminate($processData['process']);
                    }

                    proc_close($processData['process']);
                }
            }

            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }
}
