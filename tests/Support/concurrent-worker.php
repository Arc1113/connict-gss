<?php

use App\Actions\Admin\CeremonyAdministration;
use App\Actions\CompleteCurrentPresentation;
use App\Actions\ScanStudentForPresentation;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$operation = $argv[1];
$startFile = $argv[2];
$resultFile = $argv[3];
$arguments = array_slice($argv, 4);

while (! file_exists($startFile)) {
    usleep(1_000);
}

try {
    $result = match ($operation) {
        'scan' => app(ScanStudentForPresentation::class)
            ->execute(
                $arguments[0],
                User::query()->findOrFail((int) $arguments[1]),
            )
            ->toArray(),
        'complete' => app(CompleteCurrentPresentation::class)
            ->execute(
                User::query()->findOrFail((int) $arguments[0]),
            )['data'],
        'activate_ceremony' => activateCeremony(
            (int) $arguments[0],
            (int) $arguments[1],
        ),
        default => throw new RuntimeException('Unsupported concurrency operation.'),
    };

    file_put_contents(
        $resultFile,
        json_encode($result, JSON_THROW_ON_ERROR),
    );
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}

/**
 * @return array{status: string, ceremony_id: int}
 */
function activateCeremony(int $ceremonyId, int $actorId): array
{
    $ceremony = Ceremony::query()->findOrFail($ceremonyId);
    $actor = User::query()->findOrFail($actorId);

    try {
        app(CeremonyAdministration::class)->update(
            $ceremony,
            [
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'venue' => $ceremony->venue,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
                'status' => 'active',
                'presentation_mode' => $ceremony->presentation_mode->value,
            ],
            $actor,
            null,
        );

        return ['status' => 'activated', 'ceremony_id' => $ceremonyId];
    } catch (ValidationException) {
        return ['status' => 'blocked', 'ceremony_id' => $ceremonyId];
    }
}
