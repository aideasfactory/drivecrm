<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Instructor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mobile app onboarding slider progress for an Instructor.
 *
 * @mixin Instructor
 */
class AppOnboardingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'current_step' => $this->app_onboarding_step,
            'total_steps' => Instructor::APP_ONBOARDING_TOTAL_STEPS,
            'completed' => $this->hasCompletedAppOnboarding(),
            'completed_at' => $this->app_onboarding_completed_at?->toIso8601String(),
        ];
    }
}
