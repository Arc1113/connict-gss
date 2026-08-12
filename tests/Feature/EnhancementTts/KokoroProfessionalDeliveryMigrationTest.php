<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Enums\AudioReadinessStatus;
use App\Enums\GraduateAudioSourceType;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KokoroProfessionalDeliveryMigrationTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    public function test_only_reviewable_kokoro_audio_is_invalidated(): void
    {
        $ceremony = Ceremony::factory()->create();
        $localTemplate = AnnouncementTemplate::factory()->for($ceremony)->create([
            'provider' => AnnouncementProvider::Kokoro,
            'default_voice' => 'am_michael',
            'default_locale' => 'en-US',
        ]);
        $azureTemplate = AnnouncementTemplate::factory()->for($ceremony)->create([
            'version' => 2,
        ]);
        $localStudent = Student::factory()->for($ceremony)->create();
        $azureStudent = Student::factory()->for($ceremony)->create();
        $localGeneration = AnnouncementGeneration::factory()
            ->approved()
            ->for($localStudent)
            ->for($ceremony)
            ->for($localTemplate, 'template')
            ->create([
                'provider' => AnnouncementProvider::Kokoro,
                'provider_voice' => 'am_michael',
                'locale' => 'en-US',
            ]);
        $azureGeneration = AnnouncementGeneration::factory()
            ->approved()
            ->for($azureStudent)
            ->for($ceremony)
            ->for($azureTemplate, 'template')
            ->create();
        $localStudent->update([
            'audio_path' => $localGeneration->approved_audio_path,
            'audio_source_type' => GraduateAudioSourceType::LocalTts,
            'audio_generation_id' => $localGeneration->id,
            'audio_source_hash' => $localGeneration->source_hash,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);
        $azureStudent->update([
            'audio_path' => $azureGeneration->approved_audio_path,
            'audio_source_type' => GraduateAudioSourceType::AzureTts,
            'audio_generation_id' => $azureGeneration->id,
            'audio_source_hash' => $azureGeneration->source_hash,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);

        $migration = require database_path(
            'migrations/2026_08_12_000001_invalidate_kokoro_audio_for_professional_mc_delivery.php',
        );
        $migration->up();

        $this->assertSame(AnnouncementGenerationStatus::Outdated, $localGeneration->fresh()->status);
        $this->assertSame(AudioReadinessStatus::NeedsReview, $localStudent->fresh()->audio_readiness_status);
        $this->assertSame(AnnouncementGenerationStatus::Approved, $azureGeneration->fresh()->status);
        $this->assertSame(AudioReadinessStatus::Approved, $azureStudent->fresh()->audio_readiness_status);
    }
}
