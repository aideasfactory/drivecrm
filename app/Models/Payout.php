<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'instructor_id',
        'amount_pence',
        'status',
        'stripe_transfer_id',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'status' => PayoutStatus::class,
            'transferred_at' => 'datetime',
        ];
    }

    /**
     * Get the lesson this payout is for.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the instructor receiving this payout.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Check if payout is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === PayoutStatus::COMPLETED;
    }

    /**
     * Check if payout is pending.
     */
    public function isPending(): bool
    {
        return $this->status === PayoutStatus::PENDING;
    }

    /**
     * Check if payout failed.
     */
    public function isFailed(): bool
    {
        return $this->status === PayoutStatus::FAILED;
    }

    /**
     * Get formatted amount (e.g., "£50.00").
     */
    public function getFormattedAmountAttribute(): string
    {
        return '£'.number_format($this->amount_pence / 100, 2);
    }
}
