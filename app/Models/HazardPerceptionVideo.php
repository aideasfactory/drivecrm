<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HazardPerceptionVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HazardPerceptionVideo extends Model
{
    /** @use HasFactory<HazardPerceptionVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'topic',
        'video_url',
        'duration_seconds',
        'is_double_hazard',
        'thumbnail_url',
        'has_recap',
        'recap_video_url',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'is_double_hazard' => 'boolean',
            'has_recap' => 'boolean',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(HazardPerceptionAttempt::class);
    }

    public function scoringZones(): HasMany
    {
        return $this->hasMany(HazardPerceptionScoringZone::class)
            ->orderBy('hazard_number')
            ->orderByDesc('score');
    }

    /**
     * Resolve a stored media value to a playable URL. Uploaded files are
     * stored as public S3 paths and resolved to their permanent public URL;
     * legacy rows that already hold a full http(s) URL pass through untouched.
     */
    public function resolveStorageUrl(?string $value): ?string
    {
        if ($value === null || Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return Storage::disk('s3')->url($value);
    }
}
