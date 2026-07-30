<?php

namespace Database\Seeders;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = config('app.development_seed_password');

        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException(
                'Set GSS_SEED_PASSWORD to a local-development password of at least 12 characters before seeding.',
            );
        }

        $accounts = [
            ['name' => 'Development Administrator', 'email' => 'admin@example.test', 'role' => UserRole::Administrator],
            ['name' => 'Development Data Encoder', 'email' => 'encoder@example.test', 'role' => UserRole::DataEncoder],
            ['name' => 'Development Scanner Operator', 'email' => 'scanner@example.test', 'role' => UserRole::ScannerOperator],
            ['name' => 'Development Stage Operator', 'email' => 'stage@example.test', 'role' => UserRole::StageOperator],
            ['name' => 'Development Technical Operator', 'email' => 'technical@example.test', 'role' => UserRole::TechnicalOperator],
        ];

        foreach ($accounts as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                    'role' => $account['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        $ceremony = Ceremony::query()->updateOrCreate(
            [
                'name' => 'Sample Graduation Ceremony',
                'academic_year' => '2026-2027',
            ],
            [
                'venue' => 'Sample University Auditorium',
                'event_date' => now()->addMonth()->toDateString(),
                'status' => CeremonyStatus::Active,
                'presentation_mode' => PresentationMode::Controlled,
                'activated_at' => now(),
                'completed_at' => null,
            ],
        );

        $students = [
            [
                'student_number' => 'DEMO-0001',
                'qr_token' => 'GSS-PHASE3-A7K9M2Q4',
                'first_name' => 'Ana Marie',
                'last_name' => 'dela Cruz',
                'program' => 'Bachelor of Science in Computer Science',
                'major' => null,
                'honors' => 'Magna Cum Laude',
                'audio_path' => 'media/phase3/audio/student-01.wav',
            ],
            [
                'student_number' => 'DEMO-0002',
                'qr_token' => 'GSS-PHASE3-B8L3N5R7',
                'first_name' => 'Christopher Emmanuel',
                'last_name' => 'Villanueva-Santos',
                'program' => 'Bachelor of Science in Business Administration',
                'major' => 'Financial Management',
                'honors' => null,
                'audio_path' => 'media/phase3/audio/student-02.wav',
            ],
            [
                'student_number' => 'DEMO-0003',
                'qr_token' => 'GSS-PHASE3-C4P6S8V2',
                'first_name' => 'Ma. Lourdes P.',
                'last_name' => 'Reyes',
                'program' => 'Bachelor of Science in Electronics Engineering',
                'major' => null,
                'honors' => 'Cum Laude · Leadership Award',
                'audio_path' => 'media/phase3/audio/student-03.wav',
            ],
            [
                'student_number' => 'DEMO-0004',
                'qr_token' => 'GSS-PHASE3-D9T5W7Y3',
                'first_name' => 'Dario',
                'last_name' => 'Navarro',
                'program' => 'Bachelor of Arts in Communication',
                'major' => 'Media Studies',
                'honors' => null,
                'audio_path' => 'media/phase3/audio/student-04.wav',
            ],
            [
                'student_number' => 'DEMO-0005',
                'qr_token' => 'GSS-PHASE3-E2H6J8X4',
                'first_name' => 'Elena Alexandria-Cassandra',
                'last_name' => 'Villanueva',
                'program' => 'Bachelor of Science in Business Administration',
                'major' => 'Financial Management',
                'honors' => 'With Academic Distinction',
                'audio_path' => null,
            ],
        ];

        foreach ($students as $index => $student) {
            Student::query()->updateOrCreate(
                [
                    'ceremony_id' => $ceremony->id,
                    'student_number' => $student['student_number'],
                ],
                [
                    'qr_token' => $student['qr_token'],
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'display_name' => $student['first_name'].' '.$student['last_name'],
                    'announcement_name' => $student['first_name'].' '.$student['last_name'],
                    'college' => 'Sample College',
                    'program' => $student['program'],
                    'major' => $student['major'],
                    'honors' => $student['honors'],
                    'audio_path' => $student['audio_path'],
                    'audio_readiness_status' => $student['audio_path'] === null
                        ? AudioReadinessStatus::Missing
                        : AudioReadinessStatus::NeedsReview,
                    'audio_uploaded_at' => $student['audio_path'] === null ? null : now(),
                    'audio_approved_at' => null,
                    'audio_approved_by' => null,
                    'audio_review_notes' => $student['audio_path'] === null
                        ? null
                        : 'Development test tone only. Replace with reviewed production audio before ceremony use.',
                    'audio_duration_seconds' => null,
                    'audio_mime_type' => $student['audio_path'] === null ? null : 'audio/wav',
                    'audio_file_size' => null,
                    'sequence_number' => $index + 1,
                    'eligibility_status' => EligibilityStatus::Eligible,
                ],
            );
        }

        StageState::query()->updateOrCreate(
            ['ceremony_id' => $ceremony->id],
            [
                'current_queue_item_id' => null,
                'display_mode' => StageDisplayMode::Idle,
                'audio_status' => StageAudioStatus::Stopped,
                'is_muted' => false,
                'automatic_paused' => false,
                'version' => 0,
            ],
        );
    }
}
