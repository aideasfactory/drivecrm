<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InstructorFinance extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'type',
        'category',
        'payment_method',
        'description',
        'amount_pence',
        'is_recurring',
        'recurrence_frequency',
        'date',
        'notes',
        'receipt_path',
        'receipt_original_name',
        'receipt_mime_type',
        'receipt_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'is_recurring' => 'boolean',
            'date' => 'date',
            'receipt_size_bytes' => 'integer',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Formatted GBP amount (e.g. "£50.00").
     */
    public function getFormattedAmountAttribute(): string
    {
        return '£'.number_format($this->amount_pence / 100, 2);
    }

    /**
     * Human-readable category label, resolved from config.
     */
    public function getCategoryLabelAttribute(): ?string
    {
        if (! $this->category) {
            return null;
        }

        $source = $this->type === 'payment' ? 'payment_categories' : 'expense_categories';

        return config("finances.{$source}.{$this->category}");
    }

    /**
     * Human-readable payment-method label, resolved from config.
     */
    public function getPaymentMethodLabelAttribute(): ?string
    {
        if (! $this->payment_method) {
            return null;
        }

        return config("finances.payment_methods.{$this->payment_method}");
    }

    /**
     * Temporary signed S3 URL for the receipt (null when no receipt).
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (! $this->receipt_path) {
            return null;
        }

        $ttlMinutes = (int) config('finances.receipt.signed_url_ttl_minutes', 20);

        return Storage::disk('s3')->temporaryUrl(
            $this->receipt_path,
            now()->addMinutes($ttlMinutes)
        );
    }

    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    public function scopeExpenses($query)
    {
        return $query->where('type', 'expense');
    }
}
