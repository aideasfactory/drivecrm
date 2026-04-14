<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;
use App\Models\ResourceFolder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BulkImportResourcesAction
{
    /**
     * Cache of resolved folder paths to avoid duplicate lookups.
     *
     * @var array<string, ResourceFolder>
     */
    private array $folderCache = [];

    /**
     * Parse and import video link resources from CSV data.
     *
     * @param  array<int, array<string, string>>  $rows  Parsed CSV rows (associative arrays)
     * @param  ResourceFolder  $folder  Target folder for all imported resources
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function __invoke(array $rows, ResourceFolder $folder): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $this->folderCache = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for header row + 0-index

            // Normalize keys to lowercase/trimmed
            $row = array_change_key_case(array_map('trim', $row), CASE_LOWER);

            // Validate the row
            $validator = Validator::make($row, [
                'title' => ['required', 'string', 'max:255'],
                'video_url' => ['required', 'url', 'max:500', 'regex:/^https?:\/\/([a-z0-9-]+\.)*(youtube\.com|youtu\.be|vimeo\.com)\/.+/i'],
                'description' => ['nullable', 'string', 'max:5000'],
                'tags' => ['nullable', 'string'],
                'folder' => ['nullable', 'string', 'max:500'],
                'thumbnail_url' => ['nullable', 'url', 'max:500'],
            ], [
                'title.required' => 'Title is required.',
                'video_url.required' => 'Video URL is required.',
                'video_url.url' => 'Video URL must be a valid URL.',
                'video_url.regex' => 'Video URL must be a YouTube or Vimeo link.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => null,
                        'message' => $message,
                    ];
                }
                $skipped++;

                continue;
            }

            $validated = $validator->validated();

            try {
                // Parse tags from comma-separated string
                $tags = null;
                if (! empty($validated['tags'])) {
                    $tags = array_map('trim', explode(',', $validated['tags']));
                    $tags = array_filter($tags); // Remove empty values
                    $tags = array_values($tags); // Re-index
                }

                // Resolve target folder from folder path (e.g. "Theory/Road Signs")
                $targetFolder = $folder;
                if (! empty($validated['folder'])) {
                    $targetFolder = $this->resolveOrCreateFolderPath($validated['folder'], $folder);
                }

                Resource::create([
                    'resource_folder_id' => $targetFolder->id,
                    'resource_type' => 'video_link',
                    'video_url' => $validated['video_url'],
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'tags' => $tags,
                    'thumbnail_url' => $validated['thumbnail_url'] ?? null,
                ]);

                $imported++;
            } catch (\Exception $e) {
                Log::error('CSV resource import row failed', [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ]);

                $errors[] = [
                    'row' => $rowNumber,
                    'field' => null,
                    'message' => 'Failed to create resource: '.$e->getMessage(),
                ];
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Resolve or create a nested folder path relative to a parent folder.
     *
     * E.g. "Theory/Road Signs" creates "Theory" under parent, then "Road Signs" under "Theory".
     */
    private function resolveOrCreateFolderPath(string $path, ResourceFolder $parent): ResourceFolder
    {
        $cacheKey = $parent->id.':'.$path;
        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        $segments = array_filter(array_map('trim', explode('/', $path)));
        $currentParent = $parent;

        foreach ($segments as $segmentName) {
            $segmentCacheKey = $currentParent->id.':'.$segmentName;

            if (isset($this->folderCache[$segmentCacheKey])) {
                $currentParent = $this->folderCache[$segmentCacheKey];

                continue;
            }

            $folder = ResourceFolder::firstOrCreate(
                [
                    'parent_id' => $currentParent->id,
                    'name' => $segmentName,
                ],
                [
                    'parent_id' => $currentParent->id,
                    'name' => $segmentName,
                ]
            );

            $this->folderCache[$segmentCacheKey] = $folder;
            $currentParent = $folder;
        }

        $this->folderCache[$cacheKey] = $currentParent;

        return $currentParent;
    }
}
