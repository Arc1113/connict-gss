<?php

namespace Database\Factories;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementGeneration>
 */
class AnnouncementGenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'ceremony_id' => fn (array $attributes) => Student::query()
                ->findOrFail($attributes['student_id'])
                ->ceremony_id,
            'announcement_template_id' => fn (array $attributes) => AnnouncementTemplate::factory()
                ->create(['ceremony_id' => $attributes['ceremony_id']])
                ->id,
            'provider' => AnnouncementProvider::Azure,
            'provider_voice' => 'en-PH-JamesNeural',
            'locale' => 'en-PH',
            'plain_text' => null,
            'ssml' => null,
            'source_hash' => fn () => hash('sha256', fake()->unique()->uuid()),
            'status' => AnnouncementGenerationStatus::Pending,
            'character_count' => null,
            'output_format' => null,
            'candidate_audio_path' => null,
            'provider_request_id' => null,
            'attempt_count' => 0,
            'generated_by' => User::factory(),
            'generated_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'failure_code' => null,
            'failure_message' => null,
            'approved_audio_path' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementGenerationStatus::Pending,
        ]);
    }

    public function needsReview(): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementGenerationStatus::NeedsReview,
            'plain_text' => 'We proudly present Fictional Graduate.',
            'character_count' => 40,
            'candidate_audio_path' => 'private/announcement-candidates/fictional-candidate.mp3',
            'attempt_count' => 1,
            'generated_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->needsReview()->state(fn (): array => [
            'status' => AnnouncementGenerationStatus::Approved,
            'approved_audio_path' => 'storage/graduates/audio/fictional-approved.mp3',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementGenerationStatus::Failed,
            'attempt_count' => 1,
            'failure_code' => 'provider_unavailable',
            'failure_message' => 'The preparation provider was unavailable.',
        ]);
    }

    public function outdated(): static
    {
        return $this->needsReview()->state(fn (): array => [
            'status' => AnnouncementGenerationStatus::Outdated,
        ]);
    }
}
