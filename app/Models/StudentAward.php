<?php

namespace App\Models;

use Database\Factories\StudentAwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'award_name',
    'spoken_text',
    'display_order',
    'is_announcement_enabled',
    'created_by',
    'updated_by',
])]
class StudentAward extends Model
{
    /** @use HasFactory<StudentAwardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_announcement_enabled' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<StudentAward>  $query
     */
    public function scopeInAnnouncementOrder(Builder $query): void
    {
        $query->orderBy('display_order')->orderBy('id');
    }

    /**
     * @param  Builder<StudentAward>  $query
     */
    public function scopeAnnouncementEnabled(Builder $query): void
    {
        $query->where('is_announcement_enabled', true);
    }
}
