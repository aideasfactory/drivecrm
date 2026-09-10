<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceFolderVisibility;
use App\Models\ResourceFolder;

class UpdateFolderAction
{
    /**
     * Update a resource folder's name and visibility.
     */
    public function __invoke(
        ResourceFolder $folder,
        string $name,
        ResourceFolderVisibility $visibility
    ): ResourceFolder {
        $folder->update([
            'name' => $name,
            'visibility' => $visibility,
        ]);

        return $folder->fresh();
    }
}
