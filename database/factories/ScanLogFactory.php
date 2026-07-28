<?php

namespace Database\Factories;

use App\Enums\ScanResult;
use App\Models\ScanLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScanLog>
 */
class ScanLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ceremony_id' => null,
            'student_id' => null,
            'device_id' => null,
            'raw_token_hash' => hash('sha256', Str::random(40)),
            'result' => ScanResult::Invalid,
            'message' => 'No matching student was found.',
            'scanned_by' => null,
            'scanned_at' => now(),
        ];
    }
}
