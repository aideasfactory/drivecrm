<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\LearnerTermsAcceptance;
use App\Models\LearnerTermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The current Learner Terms text plus the learner's status, for the app's
 * terms screen and the read-only Profile → Terms and Conditions page.
 *
 * @property array{requires_acceptance: bool, is_update: bool, current: ?LearnerTermsVersion, accepted: ?LearnerTermsAcceptance} $resource
 */
class LearnerTermsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $this->resource['current'];
        $accepted = $this->resource['accepted'];

        return [
            'version' => $current?->version,
            'published_at' => $current?->published_at?->timezone('Europe/London')->toIso8601String(),
            'body_markdown' => $current?->body,
            'privacy_policy_url' => route('legal.privacy'),
            'requires_acceptance' => $this->resource['requires_acceptance'],
            'is_update' => $this->resource['is_update'],
            'acceptance' => $accepted ? new LearnerTermsAcceptanceResource($accepted) : null,
        ];
    }
}
