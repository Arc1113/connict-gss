<?php

namespace Database\Seeders;

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
use Illuminate\Support\Str;
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

        $ceremony = Ceremony::query()->firstOrCreate(
            [
                'name' => 'Sample Graduation Ceremony',
                'academic_year' => '2026-2027',
            ],
            [
                'venue' => 'Sample University Auditorium',
                'event_date' => now()->addMonth()->toDateString(),
                'status' => CeremonyStatus::Prepared,
                'presentation_mode' => PresentationMode::Controlled,
            ],
        );

        $students = [
            ['student_number' => 'DEMO-0001', 'first_name' => 'Amara', 'last_name' => 'Santos'],
            ['student_number' => 'DEMO-0002', 'first_name' => 'Benicio', 'last_name' => 'Reyes'],
            ['student_number' => 'DEMO-0003', 'first_name' => 'Clarisse', 'last_name' => 'Mendoza'],
            ['student_number' => 'DEMO-0004', 'first_name' => 'Dario', 'last_name' => 'Navarro'],
            ['student_number' => 'DEMO-0005', 'first_name' => 'Elena', 'last_name' => 'Villanueva'],
        ];

        foreach ($students as $index => $student) {
            Student::query()->firstOrCreate(
                [
                    'ceremony_id' => $ceremony->id,
                    'student_number' => $student['student_number'],
                ],
                [
                    'qr_token' => 'GSS-DEMO-'.Str::upper(Str::random(24)),
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'display_name' => $student['first_name'].' '.$student['last_name'],
                    'announcement_name' => $student['first_name'].' '.$student['last_name'],
                    'college' => 'Sample College',
                    'program' => 'Sample Degree Program',
                    'sequence_number' => $index + 1,
                    'eligibility_status' => EligibilityStatus::Eligible,
                ],
            );
        }

        StageState::query()->firstOrCreate(
            ['ceremony_id' => $ceremony->id],
            [
                'display_mode' => StageDisplayMode::Idle,
                'audio_status' => StageAudioStatus::Stopped,
                'is_muted' => false,
                'version' => 0,
            ],
        );
    }
}
