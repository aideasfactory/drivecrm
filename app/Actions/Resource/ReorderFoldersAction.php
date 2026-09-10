<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;
use Illuminate\Support\Facades\DB;

class ReorderFoldersAction
{
    /**
     * Persist a new display order for folders that share a parent.
     *
     * `$parent` null means root-level folders. Only IDs that belong to
     * that parent are updated. Position is 0-based in the supplied order.
     *
     * @param  array<int, int>  $orderedFolderIds
     */
    public function __invoke(?ResourceFolder $parent, array $orderedFolderIds): void
    {
        DB::transaction(function () use ($parent, $orderedFolderIds): void {
            foreach ($orderedFolderIds as $position => $folderId) {
                ResourceFolder::query()
                    ->where('id', $folderId)
                    ->where('parent_id', $parent?->id)
                    ->update(['sort_order' => $position]);
            }
        });
    }
}
