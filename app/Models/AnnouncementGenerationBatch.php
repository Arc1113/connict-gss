<?php

namespace App\Models;

use App\Enums\AnnouncementBatchStatus;
use App\Enums\AnnouncementProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ceremony_id',
    'announcement_template_id',
    'provider',
    'provider_voice',
    'locale',
    'selection_hash',
    'status',
    'selected_count',
    'processed_count',
    'succeeded_count',
    'reused_count',
    'failed_count',
    'estimated_character_count',
    'requested_by',
    'cancellation_requested_at',
    'started_at',
    'completed_at',
    'last_failure_code',
    'last_failure_message',
])]
class AnnouncementGenerationBatch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider' => AnnouncementProvider::class,
            'status' => AnnouncementBatchStatus::class,
            'selected_count' => 'integer',
            'processed_count' => 'integer',
            'succeeded_count' => 'integer',
            'reused_count' => 'integer',
            'failed_count' => 'integer',
            'estimated_character_count' => 'integer',
            'cancellation_requested_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AnnouncementTemplate::class, 'announcement_template_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnnouncementGenerationBatchItem::class)
            ->orderBy('position');
    }
}
