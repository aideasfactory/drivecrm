<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Models\LearnerTermsVersion;
use App\Models\User;

class SaveLearnerTermsDraftAction
{
    /**
     * Create the draft, or update it if one exists. Published versions are
     * never touched — there is only ever one draft.
     */
    public function __invoke(string $version, string $body, User $user): LearnerTermsVersion
    {
        $draft = LearnerTermsVersion::query()->draft()->first();

        if ($draft === null) {
            return LearnerTermsVersion::query()->create([
                'version' => $version,
                'body' => $body,
                'created_by_user_id' => $user->id,
            ]);
        }

        $draft->update([
            'version' => $version,
            'body' => $body,
        ]);

        return $draft;
    }
}
