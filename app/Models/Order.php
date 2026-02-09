<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'instructor_id',
        'package_id',
        'payment_mode',
        'status',
        'stripe_payment_intent_id',
        'stripe_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_mode' => PaymentMode::class,
            'status' => OrderStatus::class,
        ];
    }

    /**
     * Get the student who enrolled.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the assigned instructor.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Get the package.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get lessons for this order.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Get lesson payments for this order.
     */
    public function lessonPayments(): HasMany
    {
        return $this->hasManyThrough(LessonPayment::class, Lesson::class);
    }

    /**
     * Check if order is active.
     */
    public function isActive(): bool
    {
        return $this->status === OrderStatus::ACTIVE;
    }

    /**
     * Check if order is pending.
     */
    public function isPending(): bool
    {
        return $this->status === OrderStatus::PENDING;
    }

    /**
     * Check if payment is upfront.
     */
    public function isUpfront(): bool
    {
        return $this->payment_mode === PaymentMode::UPFRONT;
    }

    /**
     * Check if payment is weekly.
     */
    public function isWeekly(): bool
    {
        return $this->payment_mode === PaymentMode::WEEKLY;
    }
}
