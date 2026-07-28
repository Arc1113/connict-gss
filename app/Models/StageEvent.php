<?php

namespace App\Models;

use Database\Factories\StageEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ceremony_id',
    'queue_item_id',
    'event_type',
    'payload',
    'performed_by',
    'created_at',
])]
class StageEvent extends Model
{
    /** @use HasFactory<StageEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function queueItem(): BelongsTo
    {
        return $this->belongsTo(StageQueueItem::class, 'queue_item_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
