<?php

declare(strict_types=1);

namespace App\Actions\ProgressTracker;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorFrameworkAction
{
    /**
     * Return the instructor's editable framework (non-deleted categories with
     * non-deleted subcategories), ordered by sort_order at both levels.
     */
    public function __invoke(Instructor $instructor): Collection
    {
        return $instructor
            ->progressCategories()
            ->with(['subcategories' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }
}
