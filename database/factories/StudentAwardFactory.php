<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentAward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAward>
 */
class StudentAwardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'award_name' => ucfirst(fake()->unique()->words(3, true)).' Award',
            'spoken_text' => fn (array $attributes) => 'the '.$attributes['award_name'],
            'display_order' => fake()->numberBetween(0, 20),
            'is_announcement_enabled' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => [
            'is_announcement_enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_announcement_enabled' => false,
        ]);
    }
}
