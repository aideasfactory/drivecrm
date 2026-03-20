<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'label',
        'percentage',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
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
     * Check if this discount code is active and usable.
     */
    public function isUsable(): bool
    {
        return $this->active;
    }

    /**
     * Get the formatted percentage label (e.g., "10% off").
     */
    public function getFormattedPercentageAttribute(): string
    {
        return $this->percentage . '% off';
    }
}
