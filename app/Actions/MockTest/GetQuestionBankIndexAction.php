<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTestQuestion;

class GetQuestionBankIndexAction
{
    /**
     * @return array<int, array{category: string, total_questions: int, topics: array<int, array{topic: string, total_questions: int}>}>
     */
    public function __invoke(): array
    {
        $rows = MockTestQuestion::query()
            ->selectRaw('category, topic, COUNT(*) as total')
            ->groupBy('category', 'topic')
            ->orderBy('category')
            ->orderBy('topic')
            ->get();

        return $rows->groupBy('category')
            ->map(fn ($topics, string $category): array => [
                'category' => $category,
                'total_questions' => (int) $topics->sum('total'),
                'topics' => $topics->map(fn (MockTestQuestion $row): array => [
                    'topic' => $row->topic,
                    'total_questions' => (int) $row->total,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
