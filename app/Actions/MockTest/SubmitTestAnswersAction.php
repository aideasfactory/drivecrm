<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTest;
use App\Models\MockTestAnswer;
use App\Models\MockTestQuestion;
use Illuminate\Support\Facades\DB;

class SubmitTestAnswersAction
{
    private const PASS_MARK = 43;

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
                'passed' => $correctCount >= self::PASS_MARK,
                'completed_at' => now(),
            ]);

            return $mockTest->fresh(['answers.question']);
        });
    }
}
