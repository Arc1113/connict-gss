<?php

namespace App\Models;

use App\Enums\CeremonyStatus;
use App\Enums\PresentationMode;
use Database\Factories\CeremonyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'academic_year',
    'venue',
    'event_date',
    'status',
    'presentation_mode',
    'activated_at',
    'completed_at',
])]
class Ceremony extends Model
{
    /** @use HasFactory<CeremonyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_date' => 'immutable_date',
            'status' => CeremonyStatus::class,
            'presentation_mode' => PresentationMode::class,
            'activated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(StageQueueItem::class);
    }

    public function stageState(): HasOne
    {
        return $this->hasOne(StageState::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(StageEvent::class);
    }

    public function studentImports(): HasMany
    {
        return $this->hasMany(StudentImport::class);
    }
}
