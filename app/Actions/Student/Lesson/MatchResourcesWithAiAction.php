<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Services\AiResourceMatchingService;

class MatchResourcesWithAiAction
{
    public function __construct(
        protected AiResourceMatchingService $aiService
    ) {}

    /**
     * Use AI to rank available resource tags by relevance to the lesson summary.
     *
     * @param  array<int, string>  $availableTags  All unique tags from the resources table
     * @return array<int, string> Tags ranked by relevance (most relevant first)
     */
    public function __invoke(string $lessonSummary, array $availableTags): array
    {
        if (empty($availableTags) || empty(trim($lessonSummary))) {
            return [];
        }

        return $this->aiService->rankTags($lessonSummary, $availableTags);
    }
}
