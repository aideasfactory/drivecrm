<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResourceAudience;
use App\Enums\ResourceFolderVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResourceFolder extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'sort_order',
        'visibility',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'both',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'visibility' => ResourceFolderVisibility::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ResourceFolder $folder) {
            if (empty($folder->slug)) {
                $folder->slug = Str::slug($folder->name);
            }
        });

        static::updating(function (ResourceFolder $folder) {
            if ($folder->isDirty('name') && ! $folder->isDirty('slug')) {
                $folder->slug = Str::slug($folder->name);
            }
        });
    }

    /**
     * Get the parent folder.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ResourceFolder::class, 'parent_id');
    }

    /**
     * Get the child folders.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ResourceFolder::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the resources (videos, PDFs) in this folder.
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class)->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Limit folders to those visible to the given audience.
     */
    public function scopeVisibleTo(Builder $query, ResourceAudience $audience): Builder
    {
        return $query->whereIn('visibility', ResourceFolderVisibility::valuesVisibleTo($audience));
    }

    public function isVisibleTo(ResourceAudience $audience): bool
    {
        return $this->visibility->isVisibleTo($audience);
    }

    /**
     * Get all ancestor folders (for breadcrumbs).
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            array_unshift($ancestors, $current);
            $current = $current->parent;
        }

        return $ancestors;
    }
}
