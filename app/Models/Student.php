<?php

namespace App\Models;

use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Enums\GraduateAudioSourceType;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
    'tts_spoken_name',
    'college',
    'program',
    'major',
    'honors',
    'announcement_additional_text',
    'sequence_number',
    'photo_path',
    'audio_path',
    'audio_source_type',
    'audio_generation_id',
    'audio_source_hash',
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
#[Hidden(['qr_token', 'photo_path', 'audio_path', 'audio_source_hash'])]
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
            'audio_source_type' => GraduateAudioSourceType::class,
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

    public function awards(): HasMany
    {
        return $this->hasMany(StudentAward::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function announcementAwards(): HasMany
    {
        return $this->awards()->where('is_announcement_enabled', true);
    }

    public function announcementGenerations(): HasMany
    {
        return $this->hasMany(AnnouncementGeneration::class);
    }

    public function announcementGenerationBatchItems(): HasMany
    {
        return $this->hasMany(AnnouncementGenerationBatchItem::class);
    }

    public function activeAudioGeneration(): BelongsTo
    {
        return $this->belongsTo(AnnouncementGeneration::class, 'audio_generation_id');
    }
}
