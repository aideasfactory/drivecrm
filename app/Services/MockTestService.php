<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\MockTest\GenerateRandomTestAction;
use App\Actions\MockTest\GetCategoryPerformanceAction;
use App\Actions\MockTest\GetQuestionBankIndexAction;
use App\Actions\MockTest\GetRevisionQuestionsAction;
use App\Actions\MockTest\GetTestHistoryAction;
use App\Actions\MockTest\GetTestSummaryAction;
use App\Actions\MockTest\SubmitTestAnswersAction;
use App\Models\MockTest;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MockTestService extends BaseService
{
    public function __construct(
        protected GenerateRandomTestAction $generateRandomTest,
        protected SubmitTestAnswersAction $submitTestAnswers,
        protected GetTestSummaryAction $getTestSummary,
        protected GetCategoryPerformanceAction $getCategoryPerformance,
        protected GetQuestionBankIndexAction $getQuestionBankIndex,
        protected GetRevisionQuestionsAction $getRevisionQuestions,
        protected GetTestHistoryAction $getTestHistory,
    ) {}

    public function startTest(
        Student $student,
        ?string $category = null,
        ?string $topic = null,
        string $mode = 'mock',
        ?int $questionCount = null,
    ): array {
        return ($this->generateRandomTest)($student, $category, $topic, $mode, $questionCount);
    }

    public function getTestHistory(Student $student, ?string $category = null, ?string $mode = null, int $perPage = 20): LengthAwarePaginator
    {
        return ($this->getTestHistory)($student, $category, $mode, $perPage);
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

    /**
     * Question-bank index (categories + topics + counts). The bank only
     * changes via seeding, so a shared cache with TTL expiry is sufficient —
     * no invalidation hook needed.
     *
     * @return array<int, array{category: string, total_questions: int, topics: array<int, array{topic: string, total_questions: int}>}>
     */
    public function getQuestionBankIndex(): array
    {
        $key = $this->cacheKey('mock_tests', 'bank', 'index');

        return $this->remember($key, fn (): array => ($this->getQuestionBankIndex)());
    }

    public function getRevisionQuestions(string $category, ?string $topic = null, int $perPage = 20): LengthAwarePaginator
    {
        return ($this->getRevisionQuestions)($category, $topic, $perPage);
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
            $this->cacheKey('student', $studentId, 'mock_test_summary:Mixed'),
        ]);
    }
}
