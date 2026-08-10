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
     * Scoring zone bands grouped per hazard, for post-completion (recap)
     * responses only. Anti-cheat: must never appear in anything a student
     * can read before submitting an attempt — expose it exactly where
     * recap_video_url is exposed.
     *
     * @return array{hazard_1: array<int, array{score: int, start: float, end: float}>|null, hazard_2: array<int, array{score: int, start: float, end: float}>|null}
     */
    public function scoringZonesForRecap(): array
    {
        $zonesByHazard = $this->scoringZones->groupBy('hazard_number');

        $bands = fn (int $hazardNumber): ?array => $zonesByHazard->has($hazardNumber)
            ? $zonesByHazard->get($hazardNumber)->map(fn (HazardPerceptionScoringZone $zone): array => [
                'score' => $zone->score,
                'start' => round((float) $zone->start_seconds, 2),
                'end' => round((float) $zone->end_seconds, 2),
            ])->values()->all()
            : null;

        return [
            'hazard_1' => $bands(1),
            'hazard_2' => $bands(2),
        ];
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
