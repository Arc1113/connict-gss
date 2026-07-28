<?php

namespace Database\Factories;

use App\Enums\CeremonyStatus;
use App\Enums\PresentationMode;
use App\Models\Ceremony;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ceremony>
 */
class CeremonyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Graduation Ceremony '.fake()->unique()->numerify('####'),
            'academic_year' => fake()->year().'-'.fake()->year(),
            'venue' => fake()->optional()->city(),
            'event_date' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'status' => CeremonyStatus::Draft,
            'presentation_mode' => PresentationMode::Controlled,
            'activated_at' => null,
            'completed_at' => null,
        ];
    }
}
