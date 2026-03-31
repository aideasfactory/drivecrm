<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorFinance extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'type',
        'description',
        'amount_pence',
        'is_recurring',
        'recurrence_frequency',
        'date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'is_recurring' => 'boolean',
            'date' => 'date',
        ];
    }

    /**
     * Get the instructor this finance record belongs to.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Get the formatted amount in pounds.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '£'.number_format($this->amount_pence / 100, 2);
    }

    /**
     * Scope to only payments.
     */
    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    /**
     * Scope to only expenses.
     */
    public function scopeExpenses($query)
    {
        return $query->where('type', 'expense');
    }
}
