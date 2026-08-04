<?php

namespace App\Models;

use App\Enums\AnnouncementBatchItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'announcement_generation_batch_id',
    'ceremony_id',
    'student_id',
    'position',
    'status',
    'character_count',
    'announcement_generation_id',
    'failure_code',
    'failure_message',
    'processed_at',
])]
class AnnouncementGenerationBatchItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AnnouncementBatchItemStatus::class,
            'position' => 'integer',
            'character_count' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnnouncementGenerationBatch::class, 'announcement_generation_batch_id');
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AnnouncementGeneration::class, 'announcement_generation_id');
    }
}
