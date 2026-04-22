<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProgressTracker\ReorderCategoriesRequest;
use App\Http\Requests\ProgressTracker\ReorderSubcategoriesRequest;
use App\Http\Requests\ProgressTracker\StoreCategoryRequest;
use App\Http\Requests\ProgressTracker\StoreSubcategoryRequest;
use App\Http\Requests\ProgressTracker\UpdateCategoryRequest;
use App\Http\Requests\ProgressTracker\UpdateSubcategoryRequest;
use App\Models\Instructor;
use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use App\Services\ProgressTrackerService;
use Illuminate\Http\JsonResponse;

class ProgressTrackerController extends Controller
{
    public function __construct(
        protected ProgressTrackerService $progressTrackerService,
    ) {}

    public function framework(Instructor $instructor): JsonResponse
    {
        $categories = $this->progressTrackerService->getFramework($instructor);

        return response()->json([
            'categories' => $categories->map(fn (ProgressCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'subcategories' => $category->subcategories->map(fn (ProgressSubcategory $sub): array => [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'sort_order' => $sub->sort_order,
                ])->values(),
            ])->values(),
        ]);
    }

    public function storeCategory(StoreCategoryRequest $request, Instructor $instructor): JsonResponse
    {
        $category = $this->progressTrackerService->createCategory($instructor, $request->validated('name'));

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'subcategories' => [],
            ],
        ], 201);
    }

    public function updateCategory(UpdateCategoryRequest $request, Instructor $instructor, ProgressCategory $category): JsonResponse
    {
        $this->ensureOwns($instructor, $category);

        $category = $this->progressTrackerService->updateCategory($category, $request->validated('name'));

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
            ],
        ]);
    }

    public function destroyCategory(Instructor $instructor, ProgressCategory $category): JsonResponse
    {
        $this->ensureOwns($instructor, $category);

        $this->progressTrackerService->deleteCategory($category);

        return response()->json(['message' => 'Category deleted.']);
    }

    public function storeSubcategory(StoreSubcategoryRequest $request, Instructor $instructor, ProgressCategory $category): JsonResponse
    {
        $this->ensureOwns($instructor, $category);

        $sub = $this->progressTrackerService->createSubcategory($category, $request->validated('name'));

        return response()->json([
            'subcategory' => [
                'id' => $sub->id,
                'name' => $sub->name,
                'sort_order' => $sub->sort_order,
            ],
        ], 201);
    }

    public function updateSubcategory(UpdateSubcategoryRequest $request, Instructor $instructor, ProgressSubcategory $subcategory): JsonResponse
    {
        $this->ensureOwnsSub($instructor, $subcategory);

        $sub = $this->progressTrackerService->updateSubcategory($subcategory, $request->validated('name'));

        return response()->json([
            'subcategory' => [
                'id' => $sub->id,
                'name' => $sub->name,
                'sort_order' => $sub->sort_order,
            ],
        ]);
    }

    public function destroySubcategory(Instructor $instructor, ProgressSubcategory $subcategory): JsonResponse
    {
        $this->ensureOwnsSub($instructor, $subcategory);

        $this->progressTrackerService->deleteSubcategory($subcategory);

        return response()->json(['message' => 'Subcategory deleted.']);
    }

    public function reorderCategories(ReorderCategoriesRequest $request, Instructor $instructor): JsonResponse
    {
        $this->progressTrackerService->reorderCategories($instructor, $request->validated('category_ids'));

        return response()->json(['message' => 'Categories reordered.']);
    }

    public function reorderSubcategories(ReorderSubcategoriesRequest $request, Instructor $instructor, ProgressCategory $category): JsonResponse
    {
        $this->ensureOwns($instructor, $category);

        $this->progressTrackerService->reorderSubcategories($category, $request->validated('subcategory_ids'));

        return response()->json(['message' => 'Subcategories reordered.']);
    }

    private function ensureOwns(Instructor $instructor, ProgressCategory $category): void
    {
        if ($category->instructor_id !== $instructor->id) {
            abort(403, 'Category does not belong to this instructor.');
        }
    }

    private function ensureOwnsSub(Instructor $instructor, ProgressSubcategory $subcategory): void
    {
        $subcategory->loadMissing('category');

        if ($subcategory->category?->instructor_id !== $instructor->id) {
            abort(403, 'Subcategory does not belong to this instructor.');
        }
    }
}
