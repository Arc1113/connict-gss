<?php

namespace App\Models;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use Database\Factories\StageStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ceremony_id',
    'current_queue_item_id',
    'display_mode',
    'audio_status',
    'is_muted',
    'automatic_paused',
    'version',
])]
class StageState extends Model
{
    /** @use HasFactory<StageStateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'display_mode' => StageDisplayMode::class,
            'audio_status' => StageAudioStatus::class,
            'is_muted' => 'boolean',
            'automatic_paused' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function currentQueueItem(): BelongsTo
    {
        return $this->belongsTo(StageQueueItem::class, 'current_queue_item_id');
    }
}
