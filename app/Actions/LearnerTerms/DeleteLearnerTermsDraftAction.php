<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Models\LearnerTermsVersion;

class DeleteLearnerTermsDraftAction
{
    /**
     * Discard the draft. Published versions can't be deleted.
     */
    public function __invoke(): void
    {
        LearnerTermsVersion::query()->draft()->delete();
    }
}
