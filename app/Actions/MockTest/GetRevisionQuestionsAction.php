<?php

declare(strict_types=1);

namespace App\Actions\MockTest;

use App\Models\MockTestQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetRevisionQuestionsAction
{
    public function __invoke(string $category, ?string $topic = null, int $perPage = 20): LengthAwarePaginator
    {
        return MockTestQuestion::query()
            ->where('category', $category)
            ->when($topic, fn ($query) => $query->where('topic', $topic))
            ->orderBy('topic')
            ->orderBy('id')
            ->paginate($perPage);
    }
}
