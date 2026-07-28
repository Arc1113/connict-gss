<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'foundation_tested',
            'entity_type' => 'system',
            'entity_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => null,
            'created_at' => now(),
        ];
    }
}
