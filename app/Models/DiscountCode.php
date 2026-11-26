<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Support\Discounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Order statuses that count as a use of a code: the booking was paid
     * (pending and expired orders were never paid for).
     *
     * @var list<OrderStatus>
     */
    public const USED_ORDER_STATUSES = [
        OrderStatus::ACTIVE,
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
    ];

    protected $fillable = [
        'code',
        'label',
        'type',
        'percentage',
        'amount_pence',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'percentage' => 'integer',
            'amount_pence' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Get orders that used this discount code.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'discount_code_id');
    }

    /**
     * Orders that used this code and were paid for.
     */
    public function usedOrders(): HasMany
    {
        return $this->orders()->whereIn('status', self::USED_ORDER_STATUSES);
    }

    /**
     * @param  Builder<DiscountCode>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Match a typed code, ignoring case and surrounding spaces.
     *
     * @param  Builder<DiscountCode>  $query
     */
    public function scopeByCode(Builder $query, string $code): void
    {
        $query->where('code', self::normaliseCode($code));
    }

    /**
     * Codes are stored upper case.
     */
    public static function normaliseCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Check if this discount code is active and usable.
     */
    public function isUsable(): bool
    {
        return $this->active;
    }

    /**
     * Get the formatted value label (e.g., "10% off" or "£10 off").
     */
    public function getFormattedPercentageAttribute(): string
    {
        return Discounts::summary([
            'type' => $this->type?->value,
            'percentage' => $this->percentage,
            'amount_pence' => $this->amount_pence,
        ]);
    }
}
