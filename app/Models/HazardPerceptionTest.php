<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HazardPerceptionTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HazardPerceptionTest extends Model
{
    /** @use HasFactory<HazardPerceptionTestFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'topic',
        'total_videos',
        'total_score',
        'max_score',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_videos' => 'integer',
            'total_score' => 'integer',
            'max_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(
            HazardPerceptionVideo::class,
            'hazard_perception_test_videos',
            'hazard_perception_test_id',
            'hazard_perception_video_id',
        )->withPivot('position')->orderByPivot('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(HazardPerceptionAttempt::class, 'hazard_perception_test_id');
    }
}
