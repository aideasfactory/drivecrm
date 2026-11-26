<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Models\LearnerTermsVersion;
use App\Models\User;
use RuntimeException;

class PublishLearnerTermsDraftAction
{
    /**
     * Publish the draft. From now on it is the current version: every learner
     * who hasn't accepted it is shown the terms screen on their next app open.
     *
     * @throws RuntimeException When there is no draft or it is empty
     */
    public function __invoke(User $user): LearnerTermsVersion
    {
        $draft = LearnerTermsVersion::query()->draft()->first();

        if ($draft === null) {
            throw new RuntimeException('There is no draft to publish.');
        }

        if (trim($draft->body) === '') {
            throw new RuntimeException('The draft has no terms text.');
        }

        $draft->update([
            'published_at' => now(),
            'published_by_user_id' => $user->id,
        ]);

        return $draft;
    }
}
