<?php

declare(strict_types=1);

namespace App\Actions\ProgressTracker;

use App\Models\Instructor;
use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use Illuminate\Support\Facades\DB;

class SeedInstructorProgressTrackerAction
{
    /**
     * Seed the default progress-tracker framework for an instructor.
     *
     * Idempotent: skips seeding if the instructor already has any categories
     * (active or soft-deleted) so we never duplicate on re-runs or backfill.
     */
    public function __invoke(Instructor $instructor): void
    {
        $alreadySeeded = ProgressCategory::withTrashed()
            ->where('instructor_id', $instructor->id)
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        $template = config('progress_tracker.default_framework', []);

        DB::transaction(function () use ($instructor, $template): void {
            foreach ($template as $categoryIndex => $categoryData) {
                $category = ProgressCategory::create([
                    'instructor_id' => $instructor->id,
                    'name' => $categoryData['name'],
                    'sort_order' => $categoryIndex,
                ]);

                foreach ($categoryData['subcategories'] ?? [] as $subIndex => $subName) {
                    ProgressSubcategory::create([
                        'progress_category_id' => $category->id,
                        'name' => $subName,
                        'sort_order' => $subIndex,
                    ]);
                }
            }
        });
    }
}
