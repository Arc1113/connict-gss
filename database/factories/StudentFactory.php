<?php

namespace Database\Factories;

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
            'eligibility_status' => EligibilityStatus::Pending,
        ];
    }
}
