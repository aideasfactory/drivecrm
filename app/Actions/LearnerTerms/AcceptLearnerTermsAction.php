<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Actions\Shared\LogActivityAction;
use App\Exceptions\LearnerTermsVersionOutdatedException;
use App\Models\LearnerTermsAcceptance;
use App\Models\LearnerTermsVersion;
use App\Models\Student;

class AcceptLearnerTermsAction
{
    public function __construct(
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Record the student's acceptance of the current version. The app sends the
     * version label it showed; if that is no longer current the acceptance is
     * refused so the learner reads the new text first. Accepting the same
     * version twice returns the original record.
     *
     * @throws LearnerTermsVersionOutdatedException
     */
    public function __invoke(
        Student $student,
        ?LearnerTermsVersion $current,
        string $shownVersion,
        string $platform,
        ?string $appVersion,
    ): LearnerTermsAcceptance {
        if ($current === null || $current->version !== $shownVersion) {
            throw new LearnerTermsVersionOutdatedException($current?->version);
        }

        $acceptance = LearnerTermsAcceptance::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'learner_terms_version_id' => $current->id,
            ],
            [
                'accepted_at' => now(),
                'platform' => $platform,
                'app_version' => $appVersion,
            ],
        );

        if ($acceptance->wasRecentlyCreated) {
            $name = trim($student->first_name.' '.$student->surname) ?: "Student #{$student->id}";

            ($this->logActivity)(
                $student,
                "{$name} accepted Learner Terms v{$current->version} in the app ({$platform}".($appVersion ? " {$appVersion}" : '').')',
                'profile',
                [
                    'learner_terms_version_id' => $current->id,
                    'version' => $current->version,
                    'platform' => $platform,
                    'app_version' => $appVersion,
                ],
                "Accepted Learner Terms v{$current->version}",
            );
        }

        return $acceptance->setRelation('version', $current);
    }
}
