<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\EligibilityStatus;
use App\Models\Ceremony;
use App\Models\Student;
use App\Services\AnnouncementCourseOpener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnnouncementCourseOpenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lowest_eligible_sequence_is_the_course_opener(): void
    {
        $ceremony = Ceremony::factory()->create();
        $opener = Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'program' => ' Bachelor  of  Science ',
            'sequence_number' => 10,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
        $later = Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'program' => 'bachelor of science',
            'sequence_number' => 20,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        $service = app(AnnouncementCourseOpener::class);

        $this->assertTrue($service->isOpener($opener));
        $this->assertFalse($service->isOpener($later));
    }

    public function test_ineligible_lower_sequence_does_not_become_the_opener(): void
    {
        $ceremony = Ceremony::factory()->create();
        $ineligible = Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'program' => 'Engineering',
            'sequence_number' => 1,
            'eligibility_status' => EligibilityStatus::Ineligible,
        ]);
        $eligible = Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'program' => 'Engineering',
            'sequence_number' => 2,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        $service = app(AnnouncementCourseOpener::class);

        $this->assertTrue($service->isOpener($eligible));
        $this->assertTrue($service->isOpener($ineligible));
    }
}
