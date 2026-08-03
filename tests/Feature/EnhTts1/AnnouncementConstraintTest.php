<?php

namespace Tests\Feature\EnhTts1;

use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentAward;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnouncementConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_award_order_is_rejected(): void
    {
        $student = Student::factory()->create();

        $this->expectException(QueryException::class);

        StudentAward::factory()->for($student)->create(['display_order' => -1]);
    }

    public function test_exact_normalized_duplicate_award_is_rejected(): void
    {
        $student = Student::factory()->create();
        StudentAward::factory()->for($student)->create([
            'award_name' => 'Fictional Leadership Award',
            'spoken_text' => 'the Fictional Leadership Award',
        ]);

        $this->expectException(QueryException::class);

        StudentAward::factory()->for($student)->create([
            'award_name' => ' fictional leadership award ',
            'spoken_text' => ' THE FICTIONAL LEADERSHIP AWARD ',
        ]);
    }

    public function test_duplicate_active_template_is_rejected(): void
    {
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create([
            'version' => 1,
        ]);

        $this->expectException(QueryException::class);

        AnnouncementTemplate::factory()->active()->for($ceremony)->create([
            'version' => 2,
        ]);
    }

    public function test_non_positive_template_version_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        AnnouncementTemplate::factory()->create(['version' => 0]);
    }

    public function test_invalid_generation_status_is_rejected(): void
    {
        $generation = AnnouncementGeneration::factory()->make();

        $this->expectException(QueryException::class);

        DB::table('announcement_generations')->insert([
            ...$generation->getAttributes(),
            'status' => 'generated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_negative_character_count_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        AnnouncementGeneration::factory()->create(['character_count' => -1]);
    }

    public function test_negative_attempt_count_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        AnnouncementGeneration::factory()->create(['attempt_count' => -1]);
    }

    public function test_cross_ceremony_student_generation_is_rejected(): void
    {
        $firstCeremony = Ceremony::factory()->create();
        $secondCeremony = Ceremony::factory()->create();
        $student = Student::factory()->for($firstCeremony)->create();
        $template = AnnouncementTemplate::factory()->for($secondCeremony)->create();

        $this->expectException(QueryException::class);

        AnnouncementGeneration::factory()->create([
            'ceremony_id' => $secondCeremony->id,
            'student_id' => $student->id,
            'announcement_template_id' => $template->id,
        ]);
    }

    public function test_cross_ceremony_template_generation_is_rejected(): void
    {
        $firstCeremony = Ceremony::factory()->create();
        $secondCeremony = Ceremony::factory()->create();
        $student = Student::factory()->for($firstCeremony)->create();
        $template = AnnouncementTemplate::factory()->for($secondCeremony)->create();

        $this->expectException(QueryException::class);

        AnnouncementGeneration::factory()->create([
            'ceremony_id' => $firstCeremony->id,
            'student_id' => $student->id,
            'announcement_template_id' => $template->id,
        ]);
    }

    public function test_invalid_provider_is_rejected(): void
    {
        $generation = AnnouncementGeneration::factory()->make();

        $this->expectException(QueryException::class);

        DB::table('announcement_generations')->insert([
            ...$generation->getAttributes(),
            'provider' => 'unimplemented_provider',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_non_lowercase_sha256_source_hash_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        AnnouncementGeneration::factory()->create([
            'source_hash' => strtoupper(hash('sha256', 'fictional source')),
        ]);
    }

    public function test_student_announcement_fields_reject_raw_markup(): void
    {
        $this->expectException(QueryException::class);

        Student::factory()->create([
            'tts_spoken_name' => '<speak>Fictional Graduate</speak>',
        ]);
    }

    public function test_award_spoken_text_rejects_raw_markup(): void
    {
        $this->expectException(QueryException::class);

        StudentAward::factory()->create([
            'spoken_text' => '<break time="250ms"/>',
        ]);
    }

    public function test_template_text_rejects_raw_markup(): void
    {
        $this->expectException(QueryException::class);

        AnnouncementTemplate::factory()->create([
            'introduction_text' => '<speak>We proudly present</speak>',
        ]);
    }

    public function test_student_cannot_link_another_students_generation_as_active_audio(): void
    {
        $ceremony = Ceremony::factory()->create();
        $firstStudent = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        $secondStudent = Student::factory()->for($ceremony)->create([
            'sequence_number' => 2,
        ]);
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        $generation = AnnouncementGeneration::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $firstStudent->id,
            'announcement_template_id' => $template->id,
        ]);

        $this->expectException(QueryException::class);

        $secondStudent->update(['audio_generation_id' => $generation->id]);
    }
}
