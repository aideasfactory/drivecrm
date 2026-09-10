<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\ResourceFolder;
use Illuminate\Database\Eloquent\Collection;

class GetResourceFolderTreeAction
{
    public function __construct(
        protected PruneEmptyResourceFoldersAction $pruneEmptyResourceFolders
    ) {}

    /**
     * Get the folder tree with published student-audience resources nested inside.
     *
     * Only folders visible to students are included. Only published resources
     * with `audience = 'student'` are nested. Empty folders (no remaining
     * resources and no non-empty children) are pruned so instructor-only
     * libraries such as VTS do not appear as empty categories in the pupil app.
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

        $folders = ResourceFolder::query()
            ->whereNull('parent_id')
            ->visibleTo(ResourceAudience::STUDENT)
            ->with([
                'children' => fn ($q) => $q
                    ->visibleTo(ResourceAudience::STUDENT)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'children.resources' => $resources,
                'resources' => $resources,
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ($this->pruneEmptyResourceFolders)($folders);
    }
}
