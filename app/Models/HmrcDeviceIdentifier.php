<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HmrcDeviceIdentifier extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve (or create) the stable device identifier for a user.
     *
     * The cookie value is honoured on first sight to keep the client- and
     * server-side IDs in sync. After that, the stored row wins — a tampered
     * or rotated cookie cannot mint a new device ID.
     */
    public static function forUser(User $user, ?string $cookieValue = null): self
    {
        $existing = static::query()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->forceFill(['last_seen_at' => now()])->save();

            return $existing;
        }

        $deviceId = static::isValidUuid($cookieValue) ? $cookieValue : (string) Str::uuid();

        return static::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private static function isValidUuid(?string $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }
}
