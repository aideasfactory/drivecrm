<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

abstract class BaseService
{
    /**
     * Default cache TTL in seconds (10 minutes).
     */
    protected int $cacheTtl = 600;

    /**
     * Cache a value using the given key, or return the cached value if it exists.
     *
     * @template T
     *
     * @param  string  $key  Cache key
     * @param  callable(): T  $callback  Callback to generate the value if not cached
     * @param  int|null  $ttl  Cache TTL in seconds (null uses default)
     * @return T
     */
    protected function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return Cache::remember($key, $ttl ?? $this->cacheTtl, $callback);
    }

    /**
     * Invalidate one or more cache keys.
     *
     * @param  string|array<string>  $keys  Cache key(s) to invalidate
     */
    protected function invalidate(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Build a namespaced cache key for a model.
     *
     * Example: instructor:42:grouped_students
     */
    protected function cacheKey(string $prefix, int|string $id, string $suffix): string
    {
        return "{$prefix}:{$id}:{$suffix}";
    }
}
