<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

final class AdministrativeAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        User $actor,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $ipAddress,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->nonEmpty($oldValues),
            'new_values' => $this->nonEmpty($newValues),
            'ip_address' => $ipAddress,
            'created_at' => now()->utc(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function nonEmpty(?array $values): ?array
    {
        return $values === null || $values === [] ? null : $values;
    }
}
