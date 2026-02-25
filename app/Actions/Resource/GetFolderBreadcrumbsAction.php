<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;

class GetFolderBreadcrumbsAction
{
    /**
     * Build breadcrumb trail for a folder.
     *
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public function __invoke(?ResourceFolder $folder = null): array
    {
        if (! $folder) {
            return [];
        }

        $ancestors = $folder->getAncestors();

        $breadcrumbs = array_map(fn (ResourceFolder $ancestor) => [
            'id' => $ancestor->id,
            'name' => $ancestor->name,
            'slug' => $ancestor->slug,
        ], $ancestors);

        // Add the current folder
        $breadcrumbs[] = [
            'id' => $folder->id,
            'name' => $folder->name,
            'slug' => $folder->slug,
        ];

        return $breadcrumbs;
    }
}
