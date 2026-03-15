<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;

class UpdateResourceAction
{
    /**
     * Update a resource's metadata (title, description, tags).
     */
    public function __invoke(Resource $resource, string $title, ?string $description = null, ?array $tags = null, ?string $thumbnailUrl = null): Resource
    {
        $data = [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
        ];

        if ($resource->isVideoLink()) {
            $data['thumbnail_url'] = $thumbnailUrl;
        }

        $resource->update($data);

        return $resource->fresh();
    }
}
