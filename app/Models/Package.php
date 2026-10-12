<?php

namespace App\Models;

use App\Support\Fees;
use Illuminate\Database\Eloquent\Builder;
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
        'is_one_off',
        'visible_to_pupils',
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
            'is_one_off' => 'boolean',
            'visible_to_pupils' => 'boolean',
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
     * Packages a pupil may choose. Missing values follow the column default (visible)
     * so rows created before the attribute is hydrated stay on the public list.
     *
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeVisibleToPupils(Builder $query): Builder
    {
        return $query->where('visible_to_pupils', true);
    }

    public function isVisibleToPupils(): bool
    {
        $visible = $this->getAttribute('visible_to_pupils');

        if ($visible === null) {
            return true;
        }

        return (bool) $visible;
    }

    /**
     * Read the pupil-visibility flag from create/update input.
     *
     * @param  array<string, mixed>  $data
     */
    public static function visibilityFromInput(array $data, bool $default = true): bool
    {
        if (! array_key_exists('visible_to_pupils', $data) || $data['visible_to_pupils'] === null) {
            return $default;
        }

        return filter_var($data['visible_to_pupils'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Weekly payments need at least one follow-on lesson to invoice, so
     * single-lesson packages can only be paid in full.
     */
    public function allowsWeeklyPayment(): bool
    {
        return $this->lessons_count > 1;
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
        return '£'.number_format(Fees::bookingFee(), 2);
    }

    public function getDigitalFeeAttribute(): string
    {
        return '£'.number_format(Fees::digitalFeePerLesson() * $this->lessons_count, 2);
    }

    public function getTotalPriceAttribute(): string
    {
        $packagePrice = $this->total_price_pence / 100;
        $total = $packagePrice + Fees::bookingFee() + (Fees::digitalFeePerLesson() * $this->lessons_count);

        return '£'.number_format($total, 2);
    }

    public function getWeeklyPaymentAttribute(): string
    {
        if (! $this->lessons_count) {
            return '£'.number_format(0, 2);
        }

        $packagePrice = $this->total_price_pence / 100;
        $total = $packagePrice + Fees::bookingFee() + (Fees::digitalFeePerLesson() * $this->lessons_count);

        return '£'.number_format($total / $this->lessons_count, 2);
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
