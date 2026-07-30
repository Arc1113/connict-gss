<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function scannedQueueItems(): HasMany
    {
        return $this->hasMany(StageQueueItem::class, 'scanned_by');
    }

    public function presentedQueueItems(): HasMany
    {
        return $this->hasMany(StageQueueItem::class, 'presented_by');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'scanned_by');
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(StageEvent::class, 'performed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function studentImports(): HasMany
    {
        return $this->hasMany(StudentImport::class);
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole(UserRole::Administrator);
    }
}
