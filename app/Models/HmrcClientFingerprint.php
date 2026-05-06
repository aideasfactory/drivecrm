<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcClientFingerprint extends Model
{
    protected $fillable = [
        'hmrc_token_id',
        'screens',
        'window_size',
        'timezone',
        'browser_user_agent',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'screens' => 'array',
            'window_size' => 'array',
            'timezone' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function hmrcToken(): BelongsTo
    {
        return $this->belongsTo(HmrcToken::class);
    }

    public function isStale(int $maxAgeMinutes = 30): bool
    {
        return $this->captured_at->addMinutes($maxAgeMinutes)->isPast();
    }
}
