<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\LearnerTerms\AcceptLearnerTermsAction;
use App\Actions\LearnerTerms\DeleteLearnerTermsDraftAction;
use App\Actions\LearnerTerms\GetCurrentLearnerTermsVersionAction;
use App\Actions\LearnerTerms\GetLearnerTermsStatusAction;
use App\Actions\LearnerTerms\GetLearnerTermsVersionsAction;
use App\Actions\LearnerTerms\PublishLearnerTermsDraftAction;
use App\Actions\LearnerTerms\SaveLearnerTermsDraftAction;
use App\Exceptions\LearnerTermsVersionOutdatedException;
use App\Models\LearnerTermsAcceptance;
use App\Models\LearnerTermsVersion;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * DRIVE+ Learner Terms: versions managed in CRM Settings and acceptances
 * recorded from the student app.
 */
class LearnerTermsService extends BaseService
{
    public function __construct(
        protected GetCurrentLearnerTermsVersionAction $getCurrentVersion,
        protected GetLearnerTermsStatusAction $getStatus,
        protected AcceptLearnerTermsAction $acceptTerms,
        protected SaveLearnerTermsDraftAction $saveDraft,
        protected PublishLearnerTermsDraftAction $publishDraft,
        protected DeleteLearnerTermsDraftAction $deleteDraft,
        protected GetLearnerTermsVersionsAction $getVersions,
    ) {}

    /**
     * The current (latest published) version. Cached: read on every app open.
     */
    public function current(): ?LearnerTermsVersion
    {
        return $this->remember($this->currentKey(), fn () => ($this->getCurrentVersion)());
    }

    /**
     * @return array{requires_acceptance: bool, is_update: bool, current: ?LearnerTermsVersion, accepted: ?LearnerTermsAcceptance}
     */
    public function statusFor(Student $student): array
    {
        return ($this->getStatus)($student, $this->current());
    }

    /**
     * @throws LearnerTermsVersionOutdatedException
     */
    public function accept(Student $student, string $shownVersion, string $platform, ?string $appVersion): LearnerTermsAcceptance
    {
        // Read the current version fresh so a just-published version is never missed.
        return ($this->acceptTerms)($student, ($this->getCurrentVersion)(), $shownVersion, $platform, $appVersion);
    }

    /**
     * @return Collection<int, LearnerTermsVersion>
     */
    public function versions(): Collection
    {
        return ($this->getVersions)();
    }

    public function saveDraft(string $version, string $body, User $user): LearnerTermsVersion
    {
        return ($this->saveDraft)($version, $body, $user);
    }

    public function publishDraft(User $user): LearnerTermsVersion
    {
        $version = ($this->publishDraft)($user);

        $this->invalidate($this->currentKey());

        return $version;
    }

    public function deleteDraft(): void
    {
        ($this->deleteDraft)();
    }

    private function currentKey(): string
    {
        return $this->cacheKey('learner_terms', 'current', 'version');
    }
}
