<?php

declare(strict_types=1);

namespace App\Actions\ProgressTracker;

use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use App\Models\Student;
use App\Models\StudentProgress;
use Illuminate\Support\Collection;

class GetStudentProgressAction
{
    /**
     * Return the student's instructor's framework with the student's scores
     * merged in. Subcategories are included even if soft-deleted as long as
     * the student has an existing score against them (archived = true), so
     * the app can render historical data read-only.
     *
     * Shape: Collection<ProgressCategory> each with ->subcategories
     *        (augmented with `score` and `archived` properties).
     */
    public function __invoke(Student $student): Collection
    {
        if ($student->instructor_id === null) {
            return collect();
        }

        $scoresBySubcategoryId = StudentProgress::query()
            ->where('student_id', $student->id)
            ->pluck('score', 'progress_subcategory_id');

        $archivedSubcategoryIdsWithScores = ProgressSubcategory::onlyTrashed()
            ->whereIn('id', $scoresBySubcategoryId->keys())
            ->whereHas('category', fn ($q) => $q->where('instructor_id', $student->instructor_id))
            ->get()
            ->groupBy('progress_category_id');

        $categories = ProgressCategory::query()
            ->with([
                'subcategories' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('instructor_id', $student->instructor_id)
            ->orderBy('sort_order')
            ->get();

        foreach ($categories as $category) {
            $archivedForCategory = $archivedSubcategoryIdsWithScores->get($category->id, collect());
            $merged = $category->subcategories->map(function (ProgressSubcategory $sub) use ($scoresBySubcategoryId): ProgressSubcategory {
                $sub->setAttribute('score', $scoresBySubcategoryId->get($sub->id));
                $sub->setAttribute('archived', false);

                return $sub;
            });

            foreach ($archivedForCategory as $archivedSub) {
                $archivedSub->setAttribute('score', $scoresBySubcategoryId->get($archivedSub->id));
                $archivedSub->setAttribute('archived', true);
                $merged->push($archivedSub);
            }

            $category->setRelation('subcategories', $merged->values());
        }

        return $categories;
    }
}
