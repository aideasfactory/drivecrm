<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTest;
use App\Models\MockTestQuestion;
use App\Models\Student;

class GenerateRandomTestAction
{
    public function __invoke(Student $student, string $category, ?string $topic = null, int $count = 50): array
    {
        $questions = MockTestQuestion::query()
            ->where('category', $category)
            ->when($topic, fn ($q) => $q->where('topic', $topic))
            ->inRandomOrder()
            ->limit($count)
            ->get();

        $mockTest = MockTest::create([
            'student_id' => $student->id,
            'category' => $category,
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
