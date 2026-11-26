<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\LearnerTermsAcceptance;
use App\Models\LearnerTermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The learner's Learner Terms status, embedded in `auth/user` as `learner_terms`.
 * The app shows the terms screen whenever `requires_acceptance` is true.
 *
 * @property array{requires_acceptance: bool, is_update: bool, current: ?LearnerTermsVersion, accepted: ?LearnerTermsAcceptance} $resource
 */
class LearnerTermsStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $this->resource['current'];
        $accepted = $this->resource['accepted'];

        return [
            'requires_acceptance' => $this->resource['requires_acceptance'],
            'is_update' => $this->resource['is_update'],
            'current_version' => $current?->version,
            'current_published_at' => $current?->published_at?->timezone('Europe/London')->toIso8601String(),
            'accepted_version' => $accepted?->version?->version,
            'accepted_at' => $accepted?->accepted_at?->timezone('Europe/London')->toIso8601String(),
        ];
    }
}
