<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'order_id',
        'lesson_payment_id',
        'student_id',
        'instructor_id',
        'requested_by_user_id',
        'processed_by_user_id',
        'amount_pence',
        'status',
        'method',
        'stripe_refund_id',
        'reason',
        'requested_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'status' => RefundStatus::class,
            'method' => RefundMethod::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lessonPayment(): BelongsTo
    {
        return $this->belongsTo(LessonPayment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === RefundStatus::COMPLETED;
    }

    public function getFormattedAmountAttribute(): string
    {
        return '£'.number_format($this->amount_pence / 100, 2);
    }

    /**
     * Staff paper trail once a refund has been completed, e.g.
     * "Gavin Boak made refund on 07/08/2026 14:24".
     */
    public function paperTrail(): ?string
    {
        if (! $this->isCompleted() || ! $this->processedBy || ! $this->completed_at) {
            return null;
        }

        return $this->processedBy->name.' made refund on '.$this->completed_at->format('d/m/Y H:i');
    }
}
