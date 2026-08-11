<?php

namespace Tests\Feature\Phase7;

use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageQueueItem;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GraduateDetailInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Ceremony $ceremony;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->administrator = User::factory()->role(UserRole::Administrator)->create();
        $this->ceremony = Ceremony::factory()->create(['name' => 'Working Ceremony']);
        $this->student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'display_name' => 'Graduate B',
            'program' => 'BS Computer Science',
            'eligibility_status' => EligibilityStatus::Eligible,
            'audio_readiness_status' => AudioReadinessStatus::NeedsReview,
        ]);
    }

    public function test_overview_is_ceremony_scoped_and_contains_summary_statuses(): void
    {
        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/{$this->student->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Show')
                ->where('section', 'overview')
                ->where('student.ceremony.id', $this->ceremony->id)
                ->where('student.eligibility.label', 'Eligible')
                ->where('student.media.audio_status.value', 'needs_review')
                ->missing('student.qr_token')
                ->missing('announcement'));
    }

    public function test_cross_ceremony_detail_url_is_not_found(): void
    {
        $otherCeremony = Ceremony::factory()->create();

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$otherCeremony->id}/graduates/{$this->student->id}?section=announcement")
            ->assertNotFound();
    }

    public function test_academic_and_media_sections_have_only_their_related_data(): void
    {
        StudentAward::factory()->create(['student_id' => $this->student->id, 'award_name' => 'Research Award']);

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/{$this->student->id}?section=academic")
            ->assertInertia(fn (Assert $page) => $page
                ->where('section', 'academic')
                ->where('student.awards.0.award_name', 'Research Award')
                ->missing('announcement'));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/{$this->student->id}?section=media")
            ->assertInertia(fn (Assert $page) => $page
                ->where('section', 'media')
                ->where('student.qr_token_configured', true)
                ->missing('student.qr_token')
                ->missing('announcement'));
    }

    public function test_announcement_and_history_are_isolated_to_the_selected_graduate(): void
    {
        StageQueueItem::factory()->create(['ceremony_id' => $this->ceremony->id, 'student_id' => $this->student->id]);
        ScanLog::factory()->create(['ceremony_id' => $this->ceremony->id, 'student_id' => $this->student->id]);

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/{$this->student->id}?section=announcement")
            ->assertInertia(fn (Assert $page) => $page
                ->where('section', 'announcement')
                ->has('announcement.generations')
                ->where('workspace.ceremony.id', $this->ceremony->id));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/{$this->student->id}?section=history")
            ->assertInertia(fn (Assert $page) => $page
                ->where('section', 'history')
                ->has('student.queue_history', 1)
                ->has('student.scan_history', 1)
                ->missing('announcement'));
    }
}
