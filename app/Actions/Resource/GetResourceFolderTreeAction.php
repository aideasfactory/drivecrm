<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;
use Illuminate\Database\Eloquent\Collection;

class GetResourceFolderTreeAction
{
    /**
     * Get the full folder tree with published resources nested inside.
     *
     * Returns top-level folders with children and resources eager-loaded recursively.
     * Only published resources are included.
     *
     * @return Collection<int, ResourceFolder>
     */
    public function __invoke(): Collection
    {
        return ResourceFolder::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
                'children.resources' => fn ($q) => $q->published()->orderBy('sort_order')->orderBy('title'),
                'resources' => fn ($q) => $q->published()->orderBy('sort_order')->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
