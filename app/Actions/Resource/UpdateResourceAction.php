<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\Resource;

class UpdateResourceAction
{
    /**
     * Update a resource's metadata (title, description, tags, audience).
     */
    public function __invoke(
        Resource $resource,
        string $title,
        ?string $description = null,
        ?array $tags = null,
        ?string $thumbnailUrl = null,
        ?ResourceAudience $audience = null
    ): Resource {
        $data = [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
        ];

        if ($audience !== null) {
            $data['audience'] = $audience;
        }

        if ($resource->isVideoLink()) {
            $data['thumbnail_url'] = $thumbnailUrl;
        }

        $resource->update($data);

        return $resource->fresh();
    }
}
