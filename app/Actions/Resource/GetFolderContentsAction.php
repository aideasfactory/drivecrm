<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;

class GetFolderContentsAction
{
    /**
     * Get subfolders and resources for a given folder (or root level).
     *
     * @return array{folders: \Illuminate\Database\Eloquent\Collection, resources: \Illuminate\Database\Eloquent\Collection}
     */
    public function __invoke(?ResourceFolder $folder = null): array
    {
        if ($folder) {
            return [
                'folders' => $folder->children()->get(),
                'resources' => $folder->resources()->get(),
            ];
        }

        // Root level: folders with no parent
        return [
            'folders' => ResourceFolder::whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'resources' => collect(), // No resources at root level (must be in a folder)
        ];
    }
}
