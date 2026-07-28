<?php

namespace App\Models;

use App\Enums\StageQueueStatus;
use Database\Factories\StageQueueItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'ceremony_id',
    'student_id',
    'status',
    'queue_position',
    'scanned_at',
    'ready_at',
    'presented_at',
    'announced_at',
    'completed_at',
    'scanned_by',
    'presented_by',
])]
class StageQueueItem extends Model
{
    /** @use HasFactory<StageQueueItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StageQueueStatus::class,
            'queue_position' => 'integer',
            'scanned_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'presented_at' => 'immutable_datetime',
            'announced_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function presentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presented_by');
    }

    public function stageState(): HasOne
    {
        return $this->hasOne(StageState::class, 'current_queue_item_id');
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(StageEvent::class, 'queue_item_id');
    }
}
