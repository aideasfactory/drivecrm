<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageType;
use App\Observers\MessageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(MessageObserver::class)]
class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'from',
        'to',
        'message',
        'type',
        'meta',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'meta' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the user who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from');
    }

    /**
     * Get the user who received the message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to');
    }

    /**
     * Determine whether the recipient has read the message.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark the message as read. Idempotent — an existing read timestamp is never overwritten.
     */
    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return false;
        }

        return $this->forceFill(['read_at' => now()])->save();
    }

    /**
     * Scope the query to unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
