<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\Resource;
use Illuminate\Support\Collection;

class GetPublishedResourcesAction
{
    /**
     * Get all published resources, optionally filtered by audience.
     *
     * Ordered by folder, then admin-defined sort_order, then title.
     */
    public function __invoke(?ResourceAudience $audience = null): Collection
    {
        return Resource::query()
            ->published()
            ->when(
                $audience,
                fn ($q, $a) => $q
                    ->where('resources.audience', $a)
                    ->inVisibleFolder($a)
            )
            ->join(
                'resource_folders',
                'resource_folders.id',
                '=',
                'resources.resource_folder_id'
            )
            ->orderBy('resource_folders.sort_order')
            ->orderBy('resource_folders.name')
            ->orderBy('resources.sort_order')
            ->orderBy('resources.title')
            ->select('resources.*')
            ->get();
    }
}