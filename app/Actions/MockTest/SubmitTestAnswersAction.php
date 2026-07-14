<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTest;
use App\Models\MockTestAnswer;
use App\Models\MockTestQuestion;
use Illuminate\Support\Facades\DB;

class SubmitTestAnswersAction
{
    /**
     * DVSA pass mark is 43/50 (86%). Applied proportionally so tests shorter
     * than 50 questions (small topics, custom question_count) remain passable.
     */
    private const PASS_RATIO = 0.86;

    public function __invoke(MockTest $mockTest, array $answers): MockTest
    {
        return DB::transaction(function () use ($mockTest, $answers): MockTest {
            $questionIds = collect($answers)->pluck('question_id');
            $questions = MockTestQuestion::whereIn('id', $questionIds)
                ->get()
                ->keyBy('id');

            $correctCount = 0;
            $answerRecords = [];

            foreach ($answers as $answer) {
                $question = $questions->get($answer['question_id']);
                $isCorrect = $question && strtoupper($answer['selected_answer']) === strtoupper($question->correct_answer);

                if ($isCorrect) {
                    $correctCount++;
                }

                $answerRecords[] = [
                    'mock_test_id' => $mockTest->id,
                    'mock_test_question_id' => $answer['question_id'],
                    'selected_answer' => strtoupper($answer['selected_answer']),
                    'is_correct' => $isCorrect,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            MockTestAnswer::insert($answerRecords);

            $mockTest->update([
                'correct_answers' => $correctCount,
                'passed' => $this->hasPassed($mockTest, $correctCount),
                'completed_at' => now(),
            ]);

            return $mockTest->fresh(['answers.question']);
        });
    }

    /**
     * Practice runs are never pass/fail; mock tests pass at >= 86% of the
     * test's own length (43 correct on a full 50-question test).
     */
    private function hasPassed(MockTest $mockTest, int $correctCount): bool
    {
        if ($mockTest->mode === 'practice' || $mockTest->total_questions === 0) {
            return false;
        }

        return $correctCount >= (int) ceil($mockTest->total_questions * self::PASS_RATIO);
    }
}
