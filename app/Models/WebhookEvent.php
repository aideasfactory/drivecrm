<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Check if this event has already been processed.
     */
    public static function hasBeenProcessed(string $stripeEventId): bool
    {
        return static::where('stripe_event_id', $stripeEventId)->exists();
    }

    /**
     * Mark an event as processed.
     */
    public static function markAsProcessed(string $stripeEventId, string $type, ?array $payload = null): self
    {
        return static::create([
            'stripe_event_id' => $stripeEventId,
            'type' => $type,
            'payload' => $payload,
            'processed_at' => now(),
        ]);
    }
}
