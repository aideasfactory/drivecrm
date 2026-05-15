<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HazardPerceptionAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazardPerceptionAttempt extends Model
{
    /** @use HasFactory<HazardPerceptionAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'hazard_perception_video_id',
        'hazard_1_response_time',
        'hazard_1_score',
        'hazard_2_response_time',
        'hazard_2_score',
        'total_score',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'hazard_1_response_time' => 'decimal:2',
            'hazard_1_score' => 'integer',
            'hazard_2_response_time' => 'decimal:2',
            'hazard_2_score' => 'integer',
            'total_score' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(HazardPerceptionVideo::class, 'hazard_perception_video_id');
    }
}
