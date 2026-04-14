<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTest;
use App\Models\Student;

class GetTestSummaryAction
{
    private const TIPS = [
        'Read each question carefully — the first answer that seems right isn\'t always the best one.',
        'Eliminate obviously wrong answers first to improve your odds.',
        'Don\'t spend too long on one question — flag it and come back later.',
        'Focus on understanding the "why" behind each answer, not just memorising.',
        'Practice in short, focused sessions rather than long cramming marathons.',
        'Pay special attention to questions about stopping distances — they come up often.',
        'When unsure, think about what would be the safest action for all road users.',
        'Review your wrong answers after each test — they show your weak areas.',
        'Road sign questions are easy marks once you learn the patterns.',
        'Take a mock test under timed conditions to simulate the real exam.',
        'The real test is 57 minutes for 50 questions — that\'s over a minute per question.',
        'Get a good night\'s sleep before your actual theory test.',
        'Questions about vulnerable road users (cyclists, pedestrians) are very common.',
        'If two answers seem correct, pick the one that\'s safest and most cautious.',
        'Learn the difference between warning signs, regulatory signs, and information signs.',
        'Motorway rules are a dedicated section — know the basics of hard shoulder use.',
        'Don\'t overthink it — the theory test is about safe, sensible driving principles.',
        'Use the practice tests to identify your weakest categories, then study those.',
        'Take regular breaks during revision to keep your focus sharp.',
        'Aim for consistently scoring 46+ in mock tests before booking your real test.',
    ];

    public function __invoke(Student $student, ?string $category = null): array
    {
        $query = MockTest::where('student_id', $student->id)
            ->whereNotNull('completed_at');

        if ($category) {
            $query->where('category', $category);
        }

        $stats = $query->selectRaw('
            COUNT(*) as tests_taken,
            COALESCE(AVG((correct_answers / total_questions) * 100), 0) as average_score,
            SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as tests_passed
        ')->first();

        $recentScores = MockTest::where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        return [
            'tests_taken' => (int) $stats->tests_taken,
            'average_score' => round((float) $stats->average_score, 1),
            'tests_passed' => (int) $stats->tests_passed,
            'recent_scores' => $recentScores,
            'tip' => self::TIPS[array_rand(self::TIPS)],
        ];
    }
}
