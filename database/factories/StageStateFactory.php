<?php

namespace Database\Factories;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Models\Ceremony;
use App\Models\StageState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StageState>
 */
class StageStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ceremony_id' => Ceremony::factory(),
            'current_queue_item_id' => null,
            'display_mode' => StageDisplayMode::Idle,
            'audio_status' => StageAudioStatus::Stopped,
            'is_muted' => false,
            'version' => 0,
        ];
    }
}
