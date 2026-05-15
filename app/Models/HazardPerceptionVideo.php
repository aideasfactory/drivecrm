<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HazardPerceptionVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'hazard_1_start',
        'hazard_1_end',
        'hazard_2_start',
        'hazard_2_end',
        'is_double_hazard',
        'thumbnail_url',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'hazard_1_start' => 'decimal:2',
            'hazard_1_end' => 'decimal:2',
            'hazard_2_start' => 'decimal:2',
            'hazard_2_end' => 'decimal:2',
            'is_double_hazard' => 'boolean',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(HazardPerceptionAttempt::class);
    }
}
