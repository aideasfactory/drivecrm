<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;
use Illuminate\Database\Eloquent\Collection;

class GetResourceFolderTreeAction
{
    /**
     * Get the full folder tree with published student-audience resources nested inside.
     *
     * Returns top-level folders with children and resources eager-loaded recursively.
     * Only published resources with `audience = 'student'` are included — this feeds the
     * student mobile app's library tree, so instructor-audience resources are hidden.
     *
     * @return Collection<int, ResourceFolder>
     */
    public function __invoke(): Collection
    {
        $resources = fn ($q) => $q
            ->published()
            ->where('audience', 'student')
            ->orderBy('sort_order')
            ->orderBy('title');

        return ResourceFolder::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
                'children.resources' => $resources,
                'resources' => $resources,
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
