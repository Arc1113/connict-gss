<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'token',
    'user_id',
    'ceremony_id',
    'original_filename',
    'mode',
    'delimiter',
    'headers',
    'mapping',
    'source_content',
    'media_package_path',
    'row_count',
    'summary',
    'expires_at',
    'consumed_at',
])]
#[Hidden(['source_content', 'media_package_path'])]
class StudentImport extends Model
{
    protected static function booted(): void
    {
        static::deleted(function (StudentImport $studentImport): void {
            if ($studentImport->media_package_path !== null) {
                Storage::disk('local')
                    ->delete($studentImport->media_package_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Ceremony, $this>
     */
    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'source_content' => 'encrypted',
            'row_count' => 'integer',
            'summary' => 'array',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
