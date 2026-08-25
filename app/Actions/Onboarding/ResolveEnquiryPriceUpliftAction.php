<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Enquiry;
use App\Models\Instructor;

class ResolveEnquiryPriceUpliftAction
{
    /**
     * Resolve the per-lesson price uplift (pence) for an onboarding enquiry
     * from the instructor selected at step 2. Returns 0 when no instructor
     * has been selected yet or the instructor has no uplift configured.
     */
    public function __invoke(Enquiry $enquiry): int
    {
        $instructorId = $enquiry->getStepData(2)['instructor_id'] ?? null;

        if (! $instructorId) {
            return 0;
        }

        return (int) (Instructor::query()->whereKey($instructorId)->value('price_uplift_pence') ?? 0);
    }
}
