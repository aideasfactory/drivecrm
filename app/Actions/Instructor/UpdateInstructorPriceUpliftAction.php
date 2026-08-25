<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;

class UpdateInstructorPriceUpliftAction
{
    /**
     * Set the instructor's per-lesson price uplift (pence) applied to Drive
     * platform packages during website onboarding.
     */
    public function __invoke(Instructor $instructor, int $priceUpliftPence): Instructor
    {
        $instructor->update(['price_uplift_pence' => $priceUpliftPence]);

        return $instructor;
    }
}
