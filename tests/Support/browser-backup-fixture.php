<?php

use App\Models\AnnouncementGeneration;
use App\Models\Ceremony;
use App\Models\Student;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ceremony = Ceremony::query()
    ->where('name', 'Sample Graduation Ceremony')
    ->where('academic_year', '2026-2027')
    ->first();

if ($ceremony === null) {
    echo json_encode(['removed_generations' => 0], JSON_THROW_ON_ERROR);

    return;
}

$disk = Storage::disk((string) config('announcements.azure.candidate_disk', 'local'));
$directory = trim((string) config(
    'announcements.azure.candidate_directory',
    'announcement-candidates',
), '/');
$prefix = $directory.'/';
$generations = AnnouncementGeneration::query()
    ->where('ceremony_id', $ceremony->id)
    ->get(['id', 'candidate_audio_path']);

$paths = $generations
    ->pluck('candidate_audio_path')
    ->filter()
    ->filter(static fn (string $path): bool => str_starts_with($path, $prefix)
        && ! str_contains($path, '..'))
    ->values()
    ->all();

$generationIds = $generations->pluck('id');

$removed = DB::transaction(static function () use ($ceremony, $generationIds): int {
    if ($generationIds->isEmpty()) {
        return 0;
    }

    Student::query()
        ->where('ceremony_id', $ceremony->id)
        ->whereIn('audio_generation_id', $generationIds)
        ->update([
            'audio_generation_id' => null,
            'audio_source_hash' => null,
        ]);

    return AnnouncementGeneration::query()
        ->where('ceremony_id', $ceremony->id)
        ->delete();
});

if ($paths !== []) {
    $disk->delete($paths);
}

$expectedPaths = AnnouncementGeneration::query()
    ->whereNotNull('candidate_audio_path')
    ->pluck('candidate_audio_path')
    ->filter(static fn (string $path): bool => str_starts_with($path, $prefix)
        && ! str_contains($path, '..'))
    ->sort()
    ->values()
    ->all();
$actualPaths = $disk->allFiles($directory);
sort($actualPaths);

echo json_encode([
    'candidate_storage_consistent' => $actualPaths === $expectedPaths,
    'removed_generations' => $removed,
], JSON_THROW_ON_ERROR);
