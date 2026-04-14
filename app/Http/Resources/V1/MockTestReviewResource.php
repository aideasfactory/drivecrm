<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MockTestReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageBaseUrl = config('mock_tests.image_base_url', '/storage/mock-test-images');

        return [
            'id' => $this->id,
            'category' => $this->category,
            'topic' => $this->topic,
            'total_questions' => $this->total_questions,
            'correct_answers' => $this->correct_answers,
            'passed' => $this->passed,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'answers' => $this->whenLoaded('answers', fn () => $this->answers->map(fn ($answer) => [
                'question_id' => $answer->mock_test_question_id,
                'stem' => $answer->question->stem,
                'stem_image' => $answer->question->stem_image
                    ? "{$imageBaseUrl}/{$answer->question->category}/{$answer->question->stem_image}"
                    : null,
                'option_a' => $answer->question->option_a,
                'option_a_image' => $answer->question->option_a_image
                    ? "{$imageBaseUrl}/{$answer->question->category}/{$answer->question->option_a_image}"
                    : null,
                'option_b' => $answer->question->option_b,
                'option_b_image' => $answer->question->option_b_image
                    ? "{$imageBaseUrl}/{$answer->question->category}/{$answer->question->option_b_image}"
                    : null,
                'option_c' => $answer->question->option_c,
                'option_c_image' => $answer->question->option_c_image
                    ? "{$imageBaseUrl}/{$answer->question->category}/{$answer->question->option_c_image}"
                    : null,
                'option_d' => $answer->question->option_d,
                'option_d_image' => $answer->question->option_d_image
                    ? "{$imageBaseUrl}/{$answer->question->category}/{$answer->question->option_d_image}"
                    : null,
                'topic' => $answer->question->topic,
                'selected_answer' => $answer->selected_answer,
                'correct_answer' => $answer->question->correct_answer,
                'is_correct' => $answer->is_correct,
                'explanation' => $answer->question->explanation,
            ])),
        ];
    }
}
