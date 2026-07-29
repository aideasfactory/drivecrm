<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HazardPerceptionScoringZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazardPerceptionScoringZone extends Model
{
    /** @use HasFactory<HazardPerceptionScoringZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'hazard_perception_video_id',
        'hazard_number',
        'score',
        'start_seconds',
        'end_seconds',
    ];

    protected function casts(): array
    {
        return [
            'hazard_number' => 'integer',
            'score' => 'integer',
            'start_seconds' => 'decimal:2',
            'end_seconds' => 'decimal:2',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(HazardPerceptionVideo::class, 'hazard_perception_video_id');
    }
}
