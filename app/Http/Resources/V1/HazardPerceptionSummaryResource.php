<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HazardPerceptionSummaryResource extends JsonResource
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
            'attempts_taken' => $this->summaryData['attempts_taken'],
            'average_score' => $this->summaryData['average_score'],
            'best_score' => $this->summaryData['best_score'],
            'recent_attempts' => HazardPerceptionAttemptResource::collection($this->summaryData['recent_attempts']),
            'topic_performance' => $this->summaryData['topic_performance'],
        ];
    }
}
