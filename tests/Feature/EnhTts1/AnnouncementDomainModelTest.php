<?php

namespace Tests\Feature\EnhTts1;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Enums\GraduateAudioSourceType;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use App\Services\ActivateAnnouncementTemplate;
use App\Services\ResolveActiveAnnouncementTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AnnouncementDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_awards_are_ordered_and_disabled_awards_are_excluded(): void
    {
        $student = Student::factory()->create();
        $actor = User::factory()->create();
        $later = StudentAward::factory()->for($student)->create([
            'award_name' => 'Fictional Service Award',
            'display_order' => 2,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $disabled = StudentAward::factory()->disabled()->for($student)->create([
            'award_name' => 'Fictional Private Recognition',
            'display_order' => 1,
        ]);
        $first = StudentAward::factory()->enabled()->for($student)->create([
            'award_name' => 'Fictional Research Award',
            'display_order' => 0,
        ]);

        $this->assertSame(
            [$first->id, $disabled->id, $later->id],
            $student->awards()->pluck('id')->all(),
        );
        $this->assertSame(
            [$first->id, $later->id],
            $student->announcementAwards()->pluck('id')->all(),
        );
        $this->assertTrue($later->creator->is($actor));
        $this->assertTrue($later->updater->is($actor));
        $this->assertTrue($later->student->is($student));
    }

    public function test_template_activation_is_transactional_and_resolves_one_active_version(): void
    {
        $ceremony = Ceremony::factory()->create();
        $first = AnnouncementTemplate::factory()->active()->for($ceremony)->create([
            'version' => 1,
        ]);
        $second = AnnouncementTemplate::factory()->inactive()->for($ceremony)->create([
            'version' => 2,
        ]);

        $activated = app(ActivateAnnouncementTemplate::class)->activate($second);
        $resolved = app(ResolveActiveAnnouncementTemplate::class)->resolve($ceremony);

        $this->assertTrue($activated->is_active);
        $this->assertFalse($first->refresh()->is_active);
        $this->assertTrue($resolved?->is($second));
        $this->assertTrue($ceremony->activeAnnouncementTemplate?->is($second));
        $this->assertCount(2, $ceremony->announcementTemplates);
    }

    public function test_template_database_defaults_match_the_approved_configuration(): void
    {
        $ceremony = Ceremony::factory()->create();
        $template = AnnouncementTemplate::query()->create([
            'ceremony_id' => $ceremony->id,
            'name' => 'Approved fictional default',
        ])->refresh();

        $this->assertSame('We proudly present', $template->introduction_text);
        $this->assertSame('en-PH-JamesNeural', $template->default_voice);
        $this->assertSame('en-PH', $template->default_locale);
        $this->assertSame(-5, $template->speaking_rate_percent);
        $this->assertSame(250, $template->pause_after_name_ms);
        $this->assertSame(160, $template->pause_between_sections_ms);
        $this->assertSame(450, $template->recommended_character_limit);
        $this->assertSame(700, $template->maximum_character_limit);
        $this->assertSame(1, $template->version);
        $this->assertFalse($template->is_active);
    }

    public function test_generation_casts_relationships_and_private_serialization_work(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create();
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        $generator = User::factory()->create();
        $reviewer = User::factory()->create();
        $generation = AnnouncementGeneration::factory()->needsReview()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'announcement_template_id' => $template->id,
            'generated_by' => $generator->id,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'approved_audio_path' => 'storage/graduates/audio/private-approved.mp3',
        ]);

        $this->assertSame(AnnouncementProvider::Azure, $generation->provider);
        $this->assertSame(AnnouncementGenerationStatus::NeedsReview, $generation->status);
        $this->assertTrue($generation->student->is($student));
        $this->assertTrue($generation->ceremony->is($ceremony));
        $this->assertTrue($generation->template->is($template));
        $this->assertTrue($generation->generatingUser->is($generator));
        $this->assertTrue($generation->reviewingUser->is($reviewer));
        $this->assertTrue($student->announcementGenerations->contains($generation));
        $this->assertTrue($ceremony->announcementGenerations->contains($generation));
        $this->assertTrue($template->generations->contains($generation));
        $this->assertArrayNotHasKey('candidate_audio_path', $generation->toArray());
        $this->assertArrayNotHasKey('approved_audio_path', $generation->toArray());
    }

    public function test_generation_source_metadata_is_immutable_after_creation(): void
    {
        $generation = AnnouncementGeneration::factory()->create();
        $generation->provider_voice = 'en-PH-RosaNeural';

        $this->expectException(LogicException::class);

        $generation->save();
    }

    public function test_existing_audio_is_classified_as_manual_and_missing_audio_stays_unclassified(): void
    {
        $manual = Student::factory()->approvedAudio()->create();
        $missing = Student::factory()->create();

        $this->assertSame(GraduateAudioSourceType::ManualUpload, $manual->audio_source_type);
        $this->assertNull($manual->audio_generation_id);
        $this->assertNull($manual->audio_source_hash);
        $this->assertNull($missing->audio_source_type);
        $this->assertArrayNotHasKey('qr_token', $manual->toArray());
        $this->assertArrayNotHasKey('audio_path', $manual->toArray());
        $this->assertArrayNotHasKey('audio_source_hash', $manual->toArray());
    }

    public function test_student_can_reference_the_generation_that_supplied_active_audio(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create();
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        $generation = AnnouncementGeneration::factory()->approved()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'announcement_template_id' => $template->id,
        ]);

        $student->update([
            'audio_generation_id' => $generation->id,
            'audio_source_type' => GraduateAudioSourceType::AzureTts,
            'audio_source_hash' => $generation->source_hash,
        ]);

        $this->assertTrue($student->refresh()->activeAudioGeneration->is($generation));
    }

    public function test_awards_cascade_but_generation_history_restricts_student_deletion(): void
    {
        $ceremony = Ceremony::factory()->create();
        $studentWithAward = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        StudentAward::factory()->for($studentWithAward)->create();
        $awardId = $studentWithAward->awards()->value('id');

        $studentWithAward->delete();

        $this->assertDatabaseMissing('student_awards', ['id' => $awardId]);

        $studentWithHistory = Student::factory()->for($ceremony)->create([
            'sequence_number' => 2,
        ]);
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        AnnouncementGeneration::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $studentWithHistory->id,
            'announcement_template_id' => $template->id,
        ]);

        $this->expectException(QueryException::class);

        $studentWithHistory->delete();
    }

    public function test_generation_history_restricts_template_deletion(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create();
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        AnnouncementGeneration::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'announcement_template_id' => $template->id,
        ]);

        $this->expectException(QueryException::class);

        $template->delete();
    }

    public function test_operator_deletion_preserves_domain_records_and_nulls_actor_links(): void
    {
        $actor = User::factory()->create();
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create();
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        $award = StudentAward::factory()->for($student)->create([
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $generation = AnnouncementGeneration::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'announcement_template_id' => $template->id,
            'generated_by' => $actor->id,
        ]);

        $actor->delete();

        $this->assertNull($award->refresh()->created_by);
        $this->assertNull($award->updated_by);
        $this->assertNull($generation->refresh()->generated_by);
        $this->assertDatabaseHas('student_awards', ['id' => $award->id]);
        $this->assertDatabaseHas('announcement_generations', ['id' => $generation->id]);
    }
}
