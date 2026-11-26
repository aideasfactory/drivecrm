<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Models\LearnerTermsVersion;

class GetCurrentLearnerTermsVersionAction
{
    /**
     * The latest published Learner Terms version, or null when none is published yet.
     */
    public function __invoke(): ?LearnerTermsVersion
    {
        return LearnerTermsVersion::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }
}
