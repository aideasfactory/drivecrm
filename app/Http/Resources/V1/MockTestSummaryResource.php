<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MockTestSummaryResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $resource
     */
    public function __construct(protected array $summaryData)
    {
        parent::__construct($summaryData);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tests_taken' => $this->summaryData['tests_taken'],
            'average_score' => $this->summaryData['average_score'],
            'tests_passed' => $this->summaryData['tests_passed'],
            'recent_scores' => MockTestResource::collection($this->summaryData['recent_scores']),
            'tip' => $this->summaryData['tip'],
            'category_performance' => $this->summaryData['category_performance'],
        ];
    }
}
