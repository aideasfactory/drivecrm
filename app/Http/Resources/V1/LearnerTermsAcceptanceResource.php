<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\LearnerTermsAcceptance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearnerTermsAcceptance
 */
class LearnerTermsAcceptanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version?->version,
            'accepted_at' => $this->accepted_at?->timezone('Europe/London')->toIso8601String(),
            'platform' => $this->platform,
            'app_version' => $this->app_version,
        ];
    }
}
