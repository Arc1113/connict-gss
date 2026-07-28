<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Device '.fake()->unique()->numerify('####'),
            'device_type' => DeviceType::Scanner,
            'key_hash' => null,
            'ip_address' => null,
            'last_seen_at' => null,
            'status' => DeviceStatus::Inactive,
        ];
    }
}
