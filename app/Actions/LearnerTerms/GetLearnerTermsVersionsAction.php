<?php

declare(strict_types=1);

namespace App\Actions\LearnerTerms;

use App\Models\LearnerTermsVersion;
use Illuminate\Database\Eloquent\Collection;

class GetLearnerTermsVersionsAction
{
    /**
     * Every version, draft first, then newest published first, with the number
     * of learners who accepted each.
     *
     * @return Collection<int, LearnerTermsVersion>
     */
    public function __invoke(): Collection
    {
        return LearnerTermsVersion::query()
            ->with('publishedBy:id,name')
            ->withCount('acceptances')
            ->orderByRaw('published_at IS NOT NULL')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }
}
