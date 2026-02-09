<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'amount_pence',
        'status',
        'due_date',
        'paid_at',
        'stripe_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'status' => PaymentStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the lesson this payment belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Check if payment is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    /**
     * Check if payment is due.
     */
    public function isDue(): bool
    {
        return $this->status === PaymentStatus::DUE;
    }

    /**
     * Check if payment is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::REFUNDED;
    }

    /**
     * Get formatted amount (e.g., "£50.00").
     */
    public function getFormattedAmountAttribute(): string
    {
        return '£'.number_format($this->amount_pence / 100, 2);
    }
}
