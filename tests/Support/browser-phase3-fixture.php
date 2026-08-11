<?php

use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$approvedBy = User::query()
    ->where('email', 'admin@example.test')
    ->sole()
    ->id;

Ceremony::query()
    ->where('status', 'active')
    ->where('name', '!=', 'Sample Graduation Ceremony')
    ->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

$ceremony = Ceremony::query()->updateOrCreate(
    [
        'name' => 'Sample Graduation Ceremony',
        'academic_year' => '2026-2027',
    ],
    [
        'venue' => 'Sample University Auditorium',
        'event_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
        'presentation_mode' => 'controlled',
        'activated_at' => now(),
        'completed_at' => null,
    ],
);

StageState::query()->updateOrCreate(
    ['ceremony_id' => $ceremony->id],
    [
        'current_queue_item_id' => null,
        'display_mode' => 'idle',
        'audio_status' => 'stopped',
        'is_muted' => false,
        'automatic_paused' => false,
        'version' => 0,
    ],
);

StageEvent::query()->where('ceremony_id', $ceremony->id)->delete();
ScanLog::query()->where('ceremony_id', $ceremony->id)->delete();
StageQueueItem::query()->where('ceremony_id', $ceremony->id)->delete();

$writeStudent = static function (array $attributes, array $values): void {
    Student::query()->updateOrCreate($attributes, $values);
};

$writeStudent(
    [
        'ceremony_id' => $ceremony->id,
        'sequence_number' => 1,
    ],
    [
        'student_number' => 'DEMO-0001',
        'qr_token' => 'GSS-PHASE3-A7K9M2Q4',
        'first_name' => 'Ana Marie',
        'last_name' => 'dela Cruz',
        'display_name' => 'Ana Marie dela Cruz',
        'announcement_name' => 'Ana Marie dela Cruz',
        'college' => 'Sample College',
        'program' => 'Bachelor of Science in Computer Science',
        'major' => null,
        'honors' => 'Magna Cum Laude',
        'sequence_number' => 1,
        'eligibility_status' => 'eligible',
        'audio_path' => 'media/phase3/audio/student-01.wav',
        'audio_source_type' => 'manual_upload',
        'audio_generation_id' => null,
        'audio_source_hash' => null,
        'audio_readiness_status' => 'approved',
        'audio_uploaded_at' => now(),
        'audio_approved_at' => now(),
        'audio_approved_by' => $approvedBy,
        'audio_review_notes' => null,
        'audio_duration_seconds' => null,
        'audio_mime_type' => 'audio/wav',
        'audio_file_size' => 1024,
    ],
);

$writeStudent(
    [
        'ceremony_id' => $ceremony->id,
        'sequence_number' => 2,
    ],
    [
        'student_number' => 'DEMO-0002',
        'qr_token' => 'GSS-PHASE3-B8L3N5R7',
        'first_name' => 'Christopher Emmanuel',
        'last_name' => 'Villanueva-Santos',
        'display_name' => 'Christopher Emmanuel Villanueva-Santos',
        'announcement_name' => 'Christopher Emmanuel Villanueva-Santos',
        'college' => 'Sample College',
        'program' => 'Bachelor of Science in Business Administration',
        'major' => 'Financial Management',
        'honors' => null,
        'sequence_number' => 2,
        'eligibility_status' => 'eligible',
        'audio_path' => 'media/phase3/audio/student-02.wav',
        'audio_source_type' => 'manual_upload',
        'audio_generation_id' => null,
        'audio_source_hash' => null,
        'audio_readiness_status' => 'approved',
        'audio_uploaded_at' => now(),
        'audio_approved_at' => now(),
        'audio_approved_by' => $approvedBy,
        'audio_review_notes' => null,
        'audio_duration_seconds' => null,
        'audio_mime_type' => 'audio/wav',
        'audio_file_size' => 1024,
    ],
);

$writeStudent(
    [
        'ceremony_id' => $ceremony->id,
        'sequence_number' => 3,
    ],
    [
        'student_number' => 'DEMO-0003',
        'qr_token' => 'GSS-PHASE3-C4P6S8V2',
        'first_name' => 'Ma. Lourdes P.',
        'last_name' => 'Reyes',
        'display_name' => 'Ma. Lourdes P. Reyes',
        'announcement_name' => 'Ma. Lourdes P. Reyes',
        'college' => 'Sample College',
        'program' => 'Bachelor of Science in Electronics Engineering',
        'major' => null,
        'honors' => 'Cum Laude · Leadership Award',
        'sequence_number' => 3,
        'eligibility_status' => 'eligible',
        'audio_path' => 'media/phase3/audio/student-03.wav',
        'audio_source_type' => 'manual_upload',
        'audio_generation_id' => null,
        'audio_source_hash' => null,
        'audio_readiness_status' => 'approved',
        'audio_uploaded_at' => now(),
        'audio_approved_at' => now(),
        'audio_approved_by' => $approvedBy,
        'audio_review_notes' => null,
        'audio_duration_seconds' => null,
        'audio_mime_type' => 'audio/wav',
        'audio_file_size' => 1024,
    ],
);

$writeStudent(
    [
        'ceremony_id' => $ceremony->id,
        'sequence_number' => 4,
    ],
    [
        'student_number' => 'DEMO-0004',
        'qr_token' => 'GSS-PHASE3-D9T5W7Y3',
        'first_name' => 'Dario',
        'last_name' => 'Navarro',
        'display_name' => 'Dario Navarro',
        'announcement_name' => 'Dario Navarro',
        'college' => 'Sample College',
        'program' => 'Bachelor of Arts in Communication',
        'major' => 'Media Studies',
        'honors' => null,
        'sequence_number' => 4,
        'eligibility_status' => 'eligible',
        'audio_path' => 'media/phase3/audio/student-04.wav',
        'audio_source_type' => 'manual_upload',
        'audio_generation_id' => null,
        'audio_source_hash' => null,
        'audio_readiness_status' => 'approved',
        'audio_uploaded_at' => now(),
        'audio_approved_at' => now(),
        'audio_approved_by' => $approvedBy,
        'audio_review_notes' => null,
        'audio_duration_seconds' => null,
        'audio_mime_type' => 'audio/wav',
        'audio_file_size' => 1024,
    ],
);

$writeStudent(
    [
        'ceremony_id' => $ceremony->id,
        'sequence_number' => 5,
    ],
    [
        'student_number' => 'DEMO-0005',
        'qr_token' => 'GSS-PHASE3-E2H6J8X4',
        'first_name' => 'Elena Alexandria-Cassandra',
        'last_name' => 'Villanueva',
        'display_name' => 'Elena Alexandria-Cassandra Villanueva',
        'announcement_name' => 'Elena Alexandria-Cassandra Villanueva',
        'college' => 'Sample College',
        'program' => 'Bachelor of Science in Business Administration',
        'major' => 'Financial Management',
        'honors' => 'With Academic Distinction',
        'sequence_number' => 5,
        'eligibility_status' => 'eligible',
        'audio_path' => null,
        'audio_source_type' => null,
        'audio_generation_id' => null,
        'audio_source_hash' => null,
        'audio_readiness_status' => 'missing',
        'audio_uploaded_at' => null,
        'audio_approved_at' => null,
        'audio_approved_by' => null,
        'audio_review_notes' => null,
        'audio_duration_seconds' => null,
        'audio_mime_type' => null,
        'audio_file_size' => null,
    ],
);

Student::query()
    ->where('ceremony_id', $ceremony->id)
    ->where('student_number', 'like', 'DEMO-%')
    ->whereNotIn('sequence_number', [1, 2, 3, 4, 5])
    ->delete();

$ceremony->refresh();
$state = $ceremony->stageState()->first();

echo json_encode([
    'active_ceremony' => $ceremony->name,
    'stage_state' => $state === null ? null : [
        'display_mode' => $state->display_mode?->value,
        'current_queue_item_id' => $state->current_queue_item_id,
        'version' => $state->version,
        'audio_status' => $state->audio_status?->value,
        'automatic_paused' => $state->automatic_paused,
    ],
    'queue_count' => StageQueueItem::query()
        ->whereBelongsTo($ceremony)
        ->count(),
    'first_five' => Student::query()
        ->whereBelongsTo($ceremony)
        ->whereIn('sequence_number', [1, 2, 3, 4, 5])
        ->orderBy('sequence_number')
        ->get(['sequence_number', 'student_number', 'display_name'])
        ->toArray(),
], JSON_THROW_ON_ERROR);
