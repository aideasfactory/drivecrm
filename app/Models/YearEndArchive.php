<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class YearEndArchive extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'instructor_id',
        'tax_year_start',
        'status',
        'file_path',
        'file_size_bytes',
        'counts',
        'error_message',
        'queued_at',
        'generated_at',
        'expires_at',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'tax_year_start' => 'integer',
            'file_size_bytes' => 'integer',
            'counts' => 'array',
            'queued_at' => 'datetime',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->file_path !== null;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function taxYearLabel(): string
    {
        $start = (int) $this->tax_year_start;

        return sprintf('%d/%s', $start, substr((string) ($start + 1), -2));
    }

    public function taxYearStartDate(): Carbon
    {
        return Carbon::create((int) $this->tax_year_start, 4, 6)->startOfDay();
    }

    public function taxYearEndDate(): Carbon
    {
        return Carbon::create((int) $this->tax_year_start + 1, 4, 5)->endOfDay();
    }
}
