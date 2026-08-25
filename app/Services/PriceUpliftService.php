<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\UpdateInstructorPriceUpliftAction;
use App\Actions\Onboarding\ResolveEnquiryPriceUpliftAction;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\Package;

class PriceUpliftService extends BaseService
{
    public function __construct(
        protected ResolveEnquiryPriceUpliftAction $resolveEnquiryPriceUplift,
        protected UpdateInstructorPriceUpliftAction $updateInstructorPriceUplift,
    ) {}

    /**
     * Per-lesson uplift (pence) for an onboarding enquiry, from the
     * instructor selected at step 2 (0 when none selected / no uplift).
     */
    public function upliftForEnquiry(Enquiry $enquiry): int
    {
        return ($this->resolveEnquiryPriceUplift)($enquiry);
    }

    /**
     * Set an instructor's per-lesson price uplift (pence).
     */
    public function updateForInstructor(Instructor $instructor, int $priceUpliftPence): Instructor
    {
        return ($this->updateInstructorPriceUplift)($instructor, $priceUpliftPence);
    }

    /**
     * Apply a per-lesson uplift to a package IN MEMORY ONLY, so the model's
     * price accessors (total_price, weekly_payment, formatted_* etc.) all
     * reflect the uplifted price. The package must never be saved after this
     * — it is a display/snapshot adjustment, not a data change.
     *
     * Uplifts apply ONLY to Drive platform packages sold through website
     * onboarding — instructor bespoke packages are never uplifted.
     */
    public function applyUpliftToPackage(Package $package, int $upliftPencePerLesson): Package
    {
        if ($upliftPencePerLesson === 0 || $package->isBespokePackage()) {
            return $package;
        }

        $package->total_price_pence += $upliftPencePerLesson * $package->lessons_count;
        $package->lesson_price_pence += $upliftPencePerLesson;

        return $package;
    }
}
