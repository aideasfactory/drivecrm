<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\Resource;
use Illuminate\Support\Collection;

class GetPublishedResourcesAction
{
    /**
     * Get all published resources, optionally filtered by audience, ordered by title.
     */
    public function __invoke(?ResourceAudience $audience = null): Collection
    {
        return Resource::query()
            ->published()
            ->when($audience, fn ($q, $a) => $q->where('audience', $a))
            ->orderBy('title')
            ->get();
    }
}
