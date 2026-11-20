<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockTestQuestion extends Model
{
    /**
     * The only category pupils are given. The DVSA "Car (Cat B) QB Feb 2026"
     * spreadsheet is the source of truth for it (see mock-tests:import-car-bank).
     * Motorcycle, LGV-PCV and ADI rows are kept in the table so past answers
     * stay intact, but nothing pupil-facing reads them.
     */
    public const BANK_CATEGORY = 'Car';

    protected $fillable = [
        'item_code',
        'category',
        'topic',
        'stem',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'explanation',
        'stem_image',
        'option_a_image',
        'option_b_image',
        'option_c_image',
        'option_d_image',
    ];

    protected function casts(): array
    {
        return [
            'correct_answer' => 'string',
        ];
    }

    /**
     * Limit to the questions pupils are given (the Car bank).
     */
    public function scopeInBank(Builder $query): Builder
    {
        return $query->where('category', self::BANK_CATEGORY);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(MockTestAnswer::class);
    }
}
