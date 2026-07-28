<?php

namespace Database\Factories;

use App\Enums\StageQueueStatus;
use App\Models\StageQueueItem;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StageQueueItem>
 */
class StageQueueItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'ceremony_id' => fn (array $attributes) => Student::query()
                ->findOrFail($attributes['student_id'])
                ->ceremony_id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => null,
            'scanned_at' => now(),
            'ready_at' => null,
            'presented_at' => null,
            'announced_at' => null,
            'completed_at' => null,
            'scanned_by' => null,
            'presented_by' => null,
        ];
    }
}
