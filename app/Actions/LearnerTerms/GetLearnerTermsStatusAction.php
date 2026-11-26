<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Models\LearnerTermsAcceptance;
use App\Models\LearnerTermsVersion;
use App\Models\Student;

class GetLearnerTermsStatusAction
{
    /**
     * Whether the student must accept the current Learner Terms, plus what they
     * last accepted. `is_update` is true when they accepted an older version,
     * so the app shows the "We've updated our Learner Terms" sub-text.
     *
     * @return array{requires_acceptance: bool, is_update: bool, current: ?LearnerTermsVersion, accepted: ?LearnerTermsAcceptance}
     */
    public function __invoke(Student $student, ?LearnerTermsVersion $current): array
    {
        $latest = $student->learnerTermsAcceptances()
            ->with('version:id,version,published_at')
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();

        $acceptedCurrent = $current !== null && $student->learnerTermsAcceptances()
            ->where('learner_terms_version_id', $current->id)
            ->exists();

        $requiresAcceptance = $current !== null && ! $acceptedCurrent;

        return [
            'requires_acceptance' => $requiresAcceptance,
            'is_update' => $requiresAcceptance && $latest !== null,
            'current' => $current,
            'accepted' => $latest,
        ];
    }
}
