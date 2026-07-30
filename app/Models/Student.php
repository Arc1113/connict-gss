<?php

namespace App\Models;

use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'ceremony_id',
    'student_number',
    'qr_token',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'display_name',
    'announcement_name',
    'pronunciation_guide',
    'college',
    'program',
    'major',
    'honors',
    'sequence_number',
    'photo_path',
    'audio_path',
    'audio_readiness_status',
    'audio_uploaded_at',
    'audio_approved_at',
    'audio_approved_by',
    'audio_review_notes',
    'audio_duration_seconds',
    'audio_mime_type',
    'audio_file_size',
    'eligibility_status',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'eligibility_status' => EligibilityStatus::class,
            'audio_readiness_status' => AudioReadinessStatus::class,
            'audio_uploaded_at' => 'immutable_datetime',
            'audio_approved_at' => 'immutable_datetime',
            'audio_duration_seconds' => 'integer',
            'audio_file_size' => 'integer',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(StageQueueItem::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function latestQueueItem(): HasOne
    {
        return $this->hasOne(StageQueueItem::class)->latestOfMany();
    }

    public function audioApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audio_approved_by');
    }
}
