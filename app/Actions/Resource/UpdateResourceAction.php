<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;

class UpdateResourceAction
{
    /**
     * Update a resource's metadata (title, description, tags).
     */
    public function __invoke(Resource $resource, string $title, ?string $description = null, ?array $tags = null): Resource
    {
        $resource->update([
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
        ]);

        return $resource->fresh();
    }
}
