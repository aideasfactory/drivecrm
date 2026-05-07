<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HmrcTokenRefreshOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcTokenRefreshLog extends Model
{
    protected $fillable = [
        'user_id',
        'outcome',
        'error_code',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => HmrcTokenRefreshOutcome::class,
            'attempted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
