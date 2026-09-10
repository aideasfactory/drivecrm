<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;
use App\Models\ResourceFolder;
use Illuminate\Support\Facades\DB;

class ReorderResourcesAction
{
    /**
     * Persist a new display order for resources in a folder.
     *
     * Only IDs that belong to the folder are updated. Position is 0-based
     * in the order the IDs are supplied.
     *
     * @param  array<int, int>  $orderedResourceIds
     */
    public function __invoke(ResourceFolder $folder, array $orderedResourceIds): void
    {
        DB::transaction(function () use ($folder, $orderedResourceIds): void {
            foreach ($orderedResourceIds as $position => $resourceId) {
                Resource::query()
                    ->where('id', $resourceId)
                    ->where('resource_folder_id', $folder->id)
                    ->update(['sort_order' => $position]);
            }
        });
    }
}
