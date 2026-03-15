<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    protected $fillable = [
        'resource_folder_id',
        'title',
        'description',
        'tags',
        'resource_type',
        'video_url',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'thumbnail_path',
        'thumbnail_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the folder this resource belongs to.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(ResourceFolder::class, 'resource_folder_id');
    }

    /**
     * Check if the resource is a video link (Vimeo/YouTube).
     */
    public function isVideoLink(): bool
    {
        return $this->resource_type === 'video_link';
    }

    /**
     * Check if the resource is an uploaded file.
     */
    public function isFile(): bool
    {
        return $this->resource_type === 'file';
    }

    /**
     * Check if the resource is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    /**
     * Check if the resource is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get a human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes === null) {
            return '';
        }

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }
}
