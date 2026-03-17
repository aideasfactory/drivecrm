<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReflectiveLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'what_i_learned',
        'what_went_well',
        'what_to_improve',
        'additional_notes',
    ];

    /**
     * Get the lesson this reflective log belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
