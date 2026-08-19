<?php

namespace App\Models;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use Database\Factories\AnnouncementGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'student_id',
    'ceremony_id',
    'announcement_template_id',
    'provider',
    'provider_voice',
    'locale',
    'plain_text',
    'display_text',
    'pronunciation_manifest',
    'ssml',
    'source_hash',
    'status',
    'character_count',
    'output_format',
    'candidate_audio_path',
    'provider_request_id',
    'attempt_count',
    'generated_by',
    'generated_at',
    'reviewed_by',
    'reviewed_at',
    'review_notes',
    'failure_code',
    'failure_message',
    'approved_audio_path',
])]
#[Hidden(['candidate_audio_path', 'approved_audio_path'])]
class AnnouncementGeneration extends Model
{
    /** @use HasFactory<AnnouncementGenerationFactory> */
    use HasFactory;

    private const IMMUTABLE_SOURCE_ATTRIBUTES = [
        'student_id',
        'ceremony_id',
        'announcement_template_id',
        'provider',
        'provider_voice',
        'locale',
        'plain_text',
        'display_text',
        'pronunciation_manifest',
        'ssml',
        'source_hash',
        'character_count',
        'output_format',
        'generated_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $generation): void {
            foreach (self::IMMUTABLE_SOURCE_ATTRIBUTES as $attribute) {
                if ($generation->isDirty($attribute)) {
                    throw new LogicException(
                        "Announcement generation source metadata is immutable: {$attribute}.",
                    );
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => AnnouncementProvider::class,
            'status' => AnnouncementGenerationStatus::class,
            'character_count' => 'integer',
            'attempt_count' => 'integer',
            'pronunciation_manifest' => 'array',
            'generated_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AnnouncementTemplate::class, 'announcement_template_id');
    }

    public function generatingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function reviewingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
