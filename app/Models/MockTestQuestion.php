<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockTestQuestion extends Model
{
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

    public function answers(): HasMany
    {
        return $this->hasMany(MockTestAnswer::class);
    }
}
