<?php

namespace Tests\Feature\Domain;

use App\Enums\StageQueueStatus;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_numbers_are_unique_within_a_ceremony(): void
    {
        $ceremony = Ceremony::factory()->create();
        Student::factory()->for($ceremony)->create([
            'student_number' => '2026-0001',
            'sequence_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        Student::factory()->for($ceremony)->create([
            'student_number' => '2026-0001',
            'sequence_number' => 2,
        ]);
    }

    public function test_the_same_student_number_is_allowed_in_another_ceremony(): void
    {
        $firstCeremony = Ceremony::factory()->create();
        $secondCeremony = Ceremony::factory()->create();

        $first = Student::factory()->for($firstCeremony)->create([
            'student_number' => '2026-0001',
            'sequence_number' => 1,
        ]);
        $second = Student::factory()->for($secondCeremony)->create([
            'student_number' => '2026-0001',
            'sequence_number' => 1,
        ]);

        $this->assertNotSame($first->ceremony_id, $second->ceremony_id);
        $this->assertSame($first->student_number, $second->student_number);
    }

    public function test_qr_tokens_are_globally_unique(): void
    {
        $firstCeremony = Ceremony::factory()->create();
        $secondCeremony = Ceremony::factory()->create();
        Student::factory()->for($firstCeremony)->create([
            'qr_token' => 'GSS-GLOBAL-UNIQUE-TOKEN',
            'sequence_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        Student::factory()->for($secondCeremony)->create([
            'qr_token' => 'GSS-GLOBAL-UNIQUE-TOKEN',
            'sequence_number' => 1,
        ]);
    }

    public function test_sequence_numbers_are_unique_within_a_ceremony(): void
    {
        $ceremony = Ceremony::factory()->create();
        Student::factory()->for($ceremony)->create([
            'student_number' => '2026-0001',
            'sequence_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        Student::factory()->for($ceremony)->create([
            'student_number' => '2026-0002',
            'sequence_number' => 1,
        ]);
    }

    public function test_invalid_ceremony_statuses_are_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('ceremonies')->insert([
            'name' => 'Invalid Ceremony',
            'status' => 'running',
            'presentation_mode' => 'controlled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_presentation_modes_are_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('ceremonies')->insert([
            'name' => 'Invalid Ceremony',
            'status' => 'draft',
            'presentation_mode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_queue_statuses_are_rejected(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        DB::table('stage_queue_items')->insert([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => 'on_stage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_queue_item_cannot_pair_a_student_with_another_ceremony(): void
    {
        $studentCeremony = Ceremony::factory()->create();
        $otherCeremony = Ceremony::factory()->create();
        $student = Student::factory()->for($studentCeremony)->create([
            'sequence_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        StageQueueItem::factory()->create([
            'ceremony_id' => $otherCeremony->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_a_scan_log_cannot_pair_a_student_with_another_ceremony(): void
    {
        $studentCeremony = Ceremony::factory()->create();
        $otherCeremony = Ceremony::factory()->create();
        $student = Student::factory()->for($studentCeremony)->create([
            'sequence_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        ScanLog::factory()->create([
            'ceremony_id' => $otherCeremony->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_duplicate_active_queue_items_for_one_student_are_prevented(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Waiting,
        ]);

        $this->expectException(QueryException::class);

        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Ready,
        ]);
    }

    public function test_a_new_active_item_is_allowed_after_a_terminal_item(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Completed,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Waiting,
        ]);

        $this->assertSame(
            2,
            StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereBelongsTo($student)
                ->count(),
        );
    }

    public function test_only_one_queue_item_may_be_presenting_per_ceremony(): void
    {
        $ceremony = Ceremony::factory()->create();
        $firstStudent = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        $secondStudent = Student::factory()->for($ceremony)->create([
            'sequence_number' => 2,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $firstStudent->id,
            'status' => StageQueueStatus::Presenting,
        ]);

        $this->expectException(QueryException::class);

        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $secondStudent->id,
            'status' => StageQueueStatus::Presenting,
        ]);
    }

    public function test_different_ceremonies_may_each_have_one_presenting_item(): void
    {
        $firstCeremony = Ceremony::factory()->create();
        $secondCeremony = Ceremony::factory()->create();
        $firstStudent = Student::factory()->for($firstCeremony)->create([
            'sequence_number' => 1,
        ]);
        $secondStudent = Student::factory()->for($secondCeremony)->create([
            'sequence_number' => 1,
        ]);

        StageQueueItem::factory()->create([
            'ceremony_id' => $firstCeremony->id,
            'student_id' => $firstStudent->id,
            'status' => StageQueueStatus::Presenting,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $secondCeremony->id,
            'student_id' => $secondStudent->id,
            'status' => StageQueueStatus::Presenting,
        ]);

        $this->assertSame(
            2,
            StageQueueItem::query()
                ->where('status', StageQueueStatus::Presenting)
                ->count(),
        );
    }

    public function test_only_one_stage_state_may_exist_per_ceremony(): void
    {
        $ceremony = Ceremony::factory()->create();
        StageState::factory()->for($ceremony)->create();

        $this->expectException(QueryException::class);

        StageState::factory()->for($ceremony)->create();
    }

    public function test_stage_state_cannot_reference_another_ceremonys_queue_item(): void
    {
        $firstCeremony = Ceremony::factory()->create();
        $secondCeremony = Ceremony::factory()->create();
        $student = Student::factory()->for($firstCeremony)->create([
            'sequence_number' => 1,
        ]);
        $queueItem = StageQueueItem::factory()->create([
            'ceremony_id' => $firstCeremony->id,
            'student_id' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        StageState::factory()->for($secondCeremony)->create([
            'current_queue_item_id' => $queueItem->id,
        ]);
    }
}
