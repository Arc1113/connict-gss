<?php

namespace Database\Factories;

use App\Models\Ceremony;
use App\Models\StageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StageEvent>
 */
class StageEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ceremony_id' => Ceremony::factory(),
            'queue_item_id' => null,
            'event_type' => 'foundation_tested',
            'payload' => null,
            'performed_by' => null,
            'created_at' => now(),
        ];
    }
}
