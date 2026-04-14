<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\MockTest\GenerateRandomTestAction;
use App\Actions\MockTest\GetCategoryPerformanceAction;
use App\Actions\MockTest\GetTestSummaryAction;
use App\Actions\MockTest\SubmitTestAnswersAction;
use App\Models\MockTest;
use App\Models\Student;

class MockTestService extends BaseService
{
    public function __construct(
        protected GenerateRandomTestAction $generateRandomTest,
        protected SubmitTestAnswersAction $submitTestAnswers,
        protected GetTestSummaryAction $getTestSummary,
        protected GetCategoryPerformanceAction $getCategoryPerformance,
    ) {}

    public function startTest(Student $student, string $category, ?string $topic = null): array
    {
        return ($this->generateRandomTest)($student, $category, $topic);
    }

    public function submitAnswers(MockTest $mockTest, array $answers): MockTest
    {
        $result = ($this->submitTestAnswers)($mockTest, $answers);

        $this->invalidateSummaryCache($mockTest->student_id);

        return $result;
    }

    public function getSummary(Student $student, ?string $category = null): array
    {
        $key = $this->cacheKey('student', $student->id, 'mock_test_summary'.($category ? ":{$category}" : ''));

        return $this->remember($key, function () use ($student, $category): array {
            $summary = ($this->getTestSummary)($student, $category);
            $summary['category_performance'] = ($this->getCategoryPerformance)($student, $category);

            return $summary;
        });
    }

    public function getTestReview(MockTest $mockTest): MockTest
    {
        return $mockTest->load(['answers.question']);
    }

    public function invalidateSummaryCache(int $studentId): void
    {
        $this->invalidate([
            $this->cacheKey('student', $studentId, 'mock_test_summary'),
            $this->cacheKey('student', $studentId, 'mock_test_summary:Car'),
            $this->cacheKey('student', $studentId, 'mock_test_summary:ADI'),
            $this->cacheKey('student', $studentId, 'mock_test_summary:Motorcycle'),
            $this->cacheKey('student', $studentId, 'mock_test_summary:LGV-PCV'),
        ]);
    }
}
