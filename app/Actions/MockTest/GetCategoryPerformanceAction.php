<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTestAnswer;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetCategoryPerformanceAction
{
    public function __invoke(Student $student, ?string $category = null): Collection
    {
        return MockTestAnswer::query()
            ->join('mock_tests', 'mock_test_answers.mock_test_id', '=', 'mock_tests.id')
            ->join('mock_test_questions', 'mock_test_answers.mock_test_question_id', '=', 'mock_test_questions.id')
            ->where('mock_tests.student_id', $student->id)
            ->whereNotNull('mock_tests.completed_at')
            ->when($category, fn ($q) => $q->where('mock_test_questions.category', $category))
            ->groupBy('mock_test_questions.topic')
            ->select([
                'mock_test_questions.topic',
                DB::raw('COUNT(*) as total_answered'),
                DB::raw('SUM(CASE WHEN mock_test_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct'),
            ])
            ->get()
            ->map(fn ($row) => [
                'topic' => $row->topic,
                'total_answered' => (int) $row->total_answered,
                'correct' => (int) $row->correct,
                'percentage' => $row->total_answered > 0
                    ? round(($row->correct / $row->total_answered) * 100, 1)
                    : 0,
            ]);
    }
}
