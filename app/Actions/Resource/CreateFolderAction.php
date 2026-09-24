<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceFolderVisibility;
use App\Models\ResourceFolder;

class CreateFolderAction
{
    /**
     * Create a new resource folder.
     */
    public function __invoke(
        string $name,
        ?int $parentId = null,
        ResourceFolderVisibility $visibility = ResourceFolderVisibility::BOTH
    ): ResourceFolder {
        $sortOrder = (int) ResourceFolder::query()
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;

        return ResourceFolder::create([
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'visibility' => $visibility,
        ]);
    }
}
