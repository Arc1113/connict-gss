<?php

namespace Tests\Feature\EnhancementTts;

use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentAward;
use App\Services\PreviewGraduationAnnouncement;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AnnouncementPreviewTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    public function test_preview_resolves_active_template_and_database_award_order_without_side_effects(): void
    {
        Storage::fake('local');
        Http::fake();
        Event::fake([BroadcastEvent::class]);
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'announcement_name' => 'Fictional Graduate',
            'tts_spoken_name' => 'Reviewed Spoken Graduate',
            'program' => 'Bachelor of Fictional Studies',
        ]);
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        StudentAward::factory()->for($student)->create([
            'award_name' => 'Second Award',
            'spoken_text' => 'the Second Award',
            'display_order' => 1,
        ]);
        StudentAward::factory()->disabled()->for($student)->create([
            'award_name' => 'Private Award',
            'spoken_text' => 'the Private Award',
            'display_order' => 0,
        ]);
        StudentAward::factory()->for($student)->create([
            'award_name' => 'First Award',
            'spoken_text' => 'the First Award',
            'display_order' => 0,
        ]);
        $before = $student->fresh()->getAttributes();

        $result = app(PreviewGraduationAnnouncement::class)->preview($student);

        $this->assertTrue($result->isReady());
        $this->assertStringContainsString(
            'recipient of the First Award and the Second Award',
            $result->composition->plainText,
        );
        $this->assertStringNotContainsString('Private Award', $result->composition->plainText);
        $this->assertSame($before, $student->fresh()->getAttributes());
        $this->assertSame(0, AnnouncementGeneration::query()->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
        Http::assertNothingSent();
        Event::assertNotDispatched(BroadcastEvent::class);
    }

    public function test_missing_active_template_blocks_without_creating_any_record(): void
    {
        $student = Student::factory()->create();

        $result = app(PreviewGraduationAnnouncement::class)->preview($student);

        $this->assertFalse($result->isReady());
        $this->assertContains('missing_active_template', $this->issueCodes($result->blockingErrors));
        $this->assertNull($result->composition);
        $this->assertSame(0, AnnouncementGeneration::query()->count());
    }

    public function test_explicit_template_from_another_ceremony_is_rejected(): void
    {
        $student = Student::factory()->create();
        $template = AnnouncementTemplate::factory()->create();

        $result = app(PreviewGraduationAnnouncement::class)->preview($student, $template);

        $this->assertContains(
            'template_ceremony_mismatch',
            $this->issueCodes($result->blockingErrors),
        );
    }

    public function test_preview_needs_no_azure_configuration_or_http_request(): void
    {
        Http::fake();
        config()->set('services.azure', null);
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'program' => 'Bachelor of Fictional Studies',
        ]);
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();

        $result = app(PreviewGraduationAnnouncement::class)->preview($student);

        $this->assertTrue($result->isReady());
        Http::assertNothingSent();
    }
}
