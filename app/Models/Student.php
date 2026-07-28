<?php

namespace App\Models;

use App\Enums\EligibilityStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
