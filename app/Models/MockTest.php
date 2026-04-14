<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockTest extends Model
{
    protected $fillable = [
        'student_id',
        'category',
        'topic',
        'total_questions',
        'correct_answers',
        'passed',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_questions' => 'integer',
            'correct_answers' => 'integer',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(MockTestAnswer::class);
    }

    public function questions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MockTestQuestion::class, 'mock_test_answers')
            ->withPivot(['selected_answer', 'is_correct'])
            ->withTimestamps();
    }
}
