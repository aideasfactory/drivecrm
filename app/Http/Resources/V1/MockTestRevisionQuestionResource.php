<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/**
 * Revision-mode question. Unlike MockTestQuestionResource (active test mode),
 * this deliberately includes the correct answer — revision is a study
 * surface, not an assessment.
 */
class MockTestRevisionQuestionResource extends MockTestQuestionResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'correct_answer' => $this->correct_answer,
        ]);
    }
}
