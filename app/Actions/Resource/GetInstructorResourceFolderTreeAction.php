<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\ResourceFolder;
use Illuminate\Database\Eloquent\Collection;

class GetInstructorResourceFolderTreeAction
{
    /**
     * Get the folder tree with published resources nested inside, for the instructor app.
     *
     * Unlike GetResourceFolderTreeAction (student), this is NOT hard-filtered to
     * `audience = 'student'`. By default both student and instructor resources are
     * returned so the app's audience pills can filter client-side; an optional
     * audience narrows the result server-side. Each resource keeps its `audience`
     * field, and the student-only `is_watched` / `is_suggested` flags are omitted.
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
            ->with([
                'children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
                'children.resources' => $resources,
                'resources' => $resources,
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->prune($folders);
    }

    /**
     * Drop child folders with no resources, then top-level folders left with
     * neither their own resources nor any non-empty children.
     *
     * @param  Collection<int, ResourceFolder>  $folders
     * @return Collection<int, ResourceFolder>
     */
    protected function prune(Collection $folders): Collection
    {
        $folders->each(function (ResourceFolder $folder): void {
            $folder->setRelation(
                'children',
                $folder->children->filter(
                    fn (ResourceFolder $child) => $child->resources->isNotEmpty()
                )->values()
            );
        });

        return $folders->filter(
            fn (ResourceFolder $folder) => $folder->resources->isNotEmpty() || $folder->children->isNotEmpty()
        )->values();
    }
}
