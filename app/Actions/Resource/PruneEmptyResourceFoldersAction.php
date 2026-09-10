<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;
use Illuminate\Database\Eloquent\Collection;

class PruneEmptyResourceFoldersAction
{
    /**
     * Drop child folders with no resources, then top-level folders left with
     * neither their own resources nor any non-empty children.
     *
     * @param  Collection<int, ResourceFolder>  $folders
     * @return Collection<int, ResourceFolder>
     */
    public function __invoke(Collection $folders): Collection
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
