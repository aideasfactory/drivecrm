<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTest;
use App\Models\MockTestQuestion;
use App\Models\Student;

class GenerateRandomTestAction
{
    public function __invoke(Student $student, ?string $category = null, ?string $topic = null): array
    {
        $count = config('mock_tests.questions_per_test');

        $questions = MockTestQuestion::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($topic, fn ($q) => $q->where('topic', $topic))
            ->inRandomOrder()
            ->limit($count)
            ->get();

        $mockTest = MockTest::create([
            'student_id' => $student->id,
            'category' => $category ?? 'Mixed',
            'topic' => $topic,
            'total_questions' => $questions->count(),
            'started_at' => now(),
        ]);

        return [
            'mock_test' => $mockTest,
            'questions' => $questions,
        ];
    }
}
