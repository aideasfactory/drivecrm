<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a collection of ProgressCategory models (with subcategories augmented
 * by `score` and `archived` attributes from GetStudentProgressAction) into the
 * grouped response the mobile app consumes.
 */
class StudentProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProgressCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'subcategories' => $category->subcategories
                ->map(fn (ProgressSubcategory $sub): array => [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'sort_order' => $sub->sort_order,
                    'score' => $sub->getAttribute('score'),
                    'archived' => (bool) $sub->getAttribute('archived'),
                ])
                ->values(),
        ];
    }
}
