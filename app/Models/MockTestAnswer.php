<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockTestAnswer extends Model
{
    protected $fillable = [
        'mock_test_id',
        'mock_test_question_id',
        'selected_answer',
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'selected_answer' => 'string',
        ];
    }

    public function mockTest(): BelongsTo
    {
        return $this->belongsTo(MockTest::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MockTestQuestion::class, 'mock_test_question_id');
    }
}
