<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'name',
        'description',
        'total_price_pence',
        'lessons_count',
        'lesson_price_pence',
        'stripe_product_id',
        'stripe_price_id',
        'active',
    ];

    /**
     * Attributes to append to the model's array form.
     */
    protected $appends = [
        'formatted_total_price',
        'formatted_lesson_price',
        'booking_fee',
        'digital_fee',
        'total_price',
        'weekly_payment',
    ];

    protected function casts(): array
    {
        return [
            'total_price_pence' => 'integer',
            'lessons_count' => 'integer',
            'lesson_price_pence' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the instructor who created this bespoke package.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Get orders using this package.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if this is a platform default package.
     */
    public function isPlatformPackage(): bool
    {
        return $this->instructor_id === null;
    }

    /**
     * Check if this is an instructor bespoke package.
     */
    public function isBespokePackage(): bool
    {
        return $this->instructor_id !== null;
    }

    /**
     * Get formatted total price (e.g., "£500.00").
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return '£'.number_format($this->total_price_pence / 100, 2);
    }

    /**
     * Get formatted lesson price (e.g., "£50.00").
     */
    public function getFormattedLessonPriceAttribute(): string
    {
        return '£'.number_format($this->lesson_price_pence / 100, 2);
    }

    public function getBookingFeeAttribute(): string
    {
        return '£'.number_format(9.99, 2);
    }

    public function getDigitalFeeAttribute(): string
    {
        return '£'.number_format(3.99 * $this->lessons_count, 2);
    }

    public function getTotalPriceAttribute(): string
    {
        return '£'.number_format((($this->total_price_pence / 100) + 9.99 + (3.99 * $this->lessons_count)), 2);
    }

    public function getWeeklyPaymentAttribute(): string
    {
        return '£'.number_format((($this->total_price_pence / 100) + 9.99 + (3.99 * $this->lessons_count)) / $this->lessons_count, 2);
    }

    /**
     * Calculate and set lesson_price_pence before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($package) {
            if ($package->isDirty(['total_price_pence', 'lessons_count'])) {
                $package->lesson_price_pence = (int) floor($package->total_price_pence / $package->lessons_count);
            }
        });
    }
}
