<?php

namespace Database\Factories;

use App\Enums\AnnouncementProvider;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementTemplate>
 */
class AnnouncementTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ceremony_id' => Ceremony::factory(),
            'name' => 'Ceremony announcement '.fake()->unique()->numerify('####'),
            'introduction_text' => 'We proudly present',
            'include_program' => true,
            'include_major' => true,
            'include_honors' => true,
            'include_awards' => true,
            'include_additional_text' => false,
            'awards_lead_in' => 'recipient of',
            'closing_text' => null,
            'provider' => AnnouncementProvider::Azure,
            'default_voice' => 'en-PH-JamesNeural',
            'default_locale' => 'en-PH',
            'speaking_rate_percent' => -5,
            'pause_after_name_ms' => 250,
            'pause_between_sections_ms' => 160,
            'recommended_character_limit' => 450,
            'maximum_character_limit' => 700,
            'version' => 1,
            'is_active' => false,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
