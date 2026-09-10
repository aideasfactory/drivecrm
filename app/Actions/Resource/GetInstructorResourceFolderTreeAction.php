<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\ResourceFolder;
use Illuminate\Database\Eloquent\Collection;

class GetInstructorResourceFolderTreeAction
{
    public function __construct(
        protected PruneEmptyResourceFoldersAction $pruneEmptyResourceFolders
    ) {}

    /**
     * Get the folder tree with published resources nested inside, for the instructor app.
     *
     * Only folders visible to instructors are included. Unlike
     * GetResourceFolderTreeAction (student), resources are NOT hard-filtered to
     * `audience = 'student'`. By default both student and instructor resources
     * are returned so the app's audience pills can filter client-side; an
     * optional audience narrows the result server-side. Each resource keeps its
     * `audience` field, and the student-only `is_watched` / `is_suggested`
     * flags are omitted.
     *
     * Empty folders (no resources after the optional audience filter, and no
     * non-empty children) are pruned so the app never renders empty category pills.
     *
     * @return Collection<int, ResourceFolder>
     */
    public function __invoke(?ResourceAudience $audience = null): Collection
    {
        $resources = fn ($q) => $q
            ->published()
            ->when($audience, fn ($q, $a) => $q->where('audience', $a->value))
            ->orderBy('sort_order')
            ->orderBy('title');

        $folders = ResourceFolder::query()
            ->whereNull('parent_id')
            ->visibleTo(ResourceAudience::INSTRUCTOR)
            ->with([
                'children' => fn ($q) => $q
                    ->visibleTo(ResourceAudience::INSTRUCTOR)
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
