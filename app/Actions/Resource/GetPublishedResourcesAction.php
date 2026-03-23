<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;
use Illuminate\Support\Collection;

class GetPublishedResourcesAction
{
    /**
     * Get all published resources, ordered by title.
     */
    public function __invoke(): Collection
    {
        return Resource::query()
            ->published()
            ->orderBy('title')
            ->get();
    }
}
