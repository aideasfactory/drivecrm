<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ProgressTracker\GetInstructorFrameworkAction;
use App\Actions\ProgressTracker\GetStudentProgressAction;
use App\Actions\ProgressTracker\SaveStudentProgressAction;
use App\Models\Instructor;
use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressTrackerService extends BaseService
{
    public function __construct(
        protected GetInstructorFrameworkAction $getInstructorFramework,
        protected GetStudentProgressAction $getStudentProgress,
        protected SaveStudentProgressAction $saveStudentProgress,
    ) {}

    public function getFramework(Instructor $instructor): Collection
    {
        return ($this->getInstructorFramework)($instructor);
    }

    public function createCategory(Instructor $instructor, string $name): ProgressCategory
    {
        $sortOrder = (int) $instructor->progressCategories()->max('sort_order') + 1;

        return $instructor->progressCategories()->create([
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    public function updateCategory(ProgressCategory $category, string $name): ProgressCategory
    {
        $category->update(['name' => $name]);

        return $category;
    }

    public function deleteCategory(ProgressCategory $category): void
    {
        $category->delete();
    }

    public function createSubcategory(ProgressCategory $category, string $name): ProgressSubcategory
    {
        $sortOrder = (int) $category->subcategories()->max('sort_order') + 1;

        return $category->subcategories()->create([
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    public function updateSubcategory(ProgressSubcategory $subcategory, string $name): ProgressSubcategory
    {
        $subcategory->update(['name' => $name]);

        return $subcategory;
    }

    public function deleteSubcategory(ProgressSubcategory $subcategory): void
    {
        $subcategory->delete();
    }

    /**
     * Apply a new ordering to an instructor's categories.
     *
     * @param  array<int>  $orderedCategoryIds
     */
    public function reorderCategories(Instructor $instructor, array $orderedCategoryIds): void
    {
        DB::transaction(function () use ($instructor, $orderedCategoryIds): void {
            foreach ($orderedCategoryIds as $position => $categoryId) {
                ProgressCategory::where('id', $categoryId)
                    ->where('instructor_id', $instructor->id)
                    ->update(['sort_order' => $position]);
            }
        });
    }

    /**
     * Apply a new ordering to a category's subcategories.
     *
     * @param  array<int>  $orderedSubcategoryIds
     */
    public function reorderSubcategories(ProgressCategory $category, array $orderedSubcategoryIds): void
    {
        DB::transaction(function () use ($category, $orderedSubcategoryIds): void {
            foreach ($orderedSubcategoryIds as $position => $subId) {
                ProgressSubcategory::where('id', $subId)
                    ->where('progress_category_id', $category->id)
                    ->update(['sort_order' => $position]);
            }
        });
    }

    public function getStudentProgress(Student $student): Collection
    {
        return ($this->getStudentProgress)($student);
    }

    /**
     * @param  array<array{progress_subcategory_id:int,score:int}>  $scores
     */
    public function saveStudentProgress(Student $student, array $scores): void
    {
        ($this->saveStudentProgress)($student, $scores);
    }
}
