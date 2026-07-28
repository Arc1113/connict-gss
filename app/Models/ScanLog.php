<?php

namespace App\Models;

use App\Enums\ScanResult;
use Database\Factories\ScanLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ceremony_id',
    'student_id',
    'device_id',
    'raw_token_hash',
    'result',
    'message',
    'scanned_by',
    'scanned_at',
])]
class ScanLog extends Model
{
    /** @use HasFactory<ScanLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
            'scanned_at' => 'immutable_datetime',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
