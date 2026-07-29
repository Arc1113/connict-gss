<?php

namespace Tests\Feature\Phase4;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;
use Tests\TestCase;

class CeremonyActivationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_activation_requests_leave_exactly_one_active_ceremony(): void
    {
        $this->requireProcessSupport();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $first = Ceremony::factory()->create(['status' => CeremonyStatus::Prepared]);
        $second = Ceremony::factory()->create(['status' => CeremonyStatus::Prepared]);

        $results = $this->runConcurrently([
            [$first->id, $administrator->id],
            [$second->id, $administrator->id],
        ]);

        $this->assertSame(
            ['activated', 'blocked'],
            collect($results)->pluck('status')->sort()->values()->all(),
        );
        $this->assertSame(1, Ceremony::query()
            ->where('status', CeremonyStatus::Active)
            ->count());
        $this->assertSame(1, Ceremony::query()
            ->where('status', CeremonyStatus::Prepared)
            ->count());
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function requireProcessSupport(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('The proc_open function is required for concurrency tests.');
        }
    }

    /**
     * @param  list<array{0: int, 1: int}>  $operations
     * @return list<array<string, mixed>>
     */
    private function runConcurrently(array $operations): array
    {
        $directory = sys_get_temp_dir().'/gss-ceremony-concurrency-'.bin2hex(
            random_bytes(8),
        );

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
            foreach ($operations as $index => [$ceremonyId, $actorId]) {
                $resultFile = $directory.'/result-'.$index.'.json';
                $stderrFile = $directory.'/stderr-'.$index.'.txt';
                $process = proc_open(
                    [
                        PHP_BINARY,
                        base_path('tests/Support/concurrent-worker.php'),
                        'activate_ceremony',
                        $startFile,
                        $resultFile,
                        (string) $ceremonyId,
                        (string) $actorId,
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

                    throw new RuntimeException((string) $message);
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
