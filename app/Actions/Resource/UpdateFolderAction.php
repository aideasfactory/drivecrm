<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;

class UpdateFolderAction
{
    /**
     * Rename a resource folder.
     */
    public function __invoke(ResourceFolder $folder, string $name): ResourceFolder
    {
        $folder->update(['name' => $name]);

        return $folder->fresh();
    }
}
