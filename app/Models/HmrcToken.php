<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HmrcToken extends Model
{
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'token_type',
        'scopes',
        'expires_at',
        'refresh_expires_at',
        'last_refreshed_at',
        'last_expiry_warning_at',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_expiry_warning_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refreshLogs(): HasMany
    {
        return $this->hasMany(HmrcTokenRefreshLog::class, 'user_id', 'user_id');
    }

    public function isAccessTokenExpired(int $bufferSeconds = 0): bool
    {
        return $this->expires_at->subSeconds($bufferSeconds)->isPast();
    }

    public function isRefreshTokenExpired(): bool
    {
        return $this->refresh_expires_at->isPast();
    }

    public function daysUntilRefreshExpiry(): int
    {
        return (int) max(0, now()->diffInDays($this->refresh_expires_at, false));
    }
}
