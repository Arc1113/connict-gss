<?php

namespace Database\Factories;

use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Models\Ceremony;
use App\Models\Student;
use App\Services\StudentQrTokenGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ceremony_id' => Ceremony::factory(),
            'student_number' => fake()->unique()->numerify('########'),
            'qr_token' => StudentQrTokenGenerator::PREFIX.Str::upper(
                Str::random(StudentQrTokenGenerator::RANDOM_LENGTH),
            ),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'III']),
            'display_name' => fn (array $attributes) => trim(
                $attributes['first_name'].' '.$attributes['last_name'],
            ),
            'announcement_name' => fn (array $attributes) => trim(
                $attributes['first_name'].' '.$attributes['last_name'],
            ),
            'pronunciation_guide' => null,
            'college' => fake()->optional()->randomElement([
                'College of Arts and Sciences',
                'College of Engineering',
                'College of Education',
            ]),
            'program' => fake()->optional()->words(3, true),
            'major' => null,
            'honors' => null,
            'sequence_number' => fake()->unique()->numberBetween(1, 100000),
            'photo_path' => null,
            'audio_path' => null,
            'audio_readiness_status' => AudioReadinessStatus::Missing,
            'audio_uploaded_at' => null,
            'audio_approved_at' => null,
            'audio_approved_by' => null,
            'audio_review_notes' => null,
            'audio_duration_seconds' => null,
            'audio_mime_type' => null,
            'audio_file_size' => null,
            'eligibility_status' => EligibilityStatus::Pending,
        ];
    }

    public function approvedAudio(string $path = 'media/phase3/audio/student-01.wav'): static
    {
        return $this->state(fn (array $attributes) => [
            'audio_path' => $path,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
            'audio_uploaded_at' => now(),
            'audio_approved_at' => now(),
            'audio_mime_type' => str_ends_with($path, '.wav')
                ? 'audio/wav'
                : 'audio/mpeg',
            'audio_file_size' => 1024,
        ]);
    }
}
