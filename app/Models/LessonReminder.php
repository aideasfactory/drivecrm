<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonReminder extends Model
{
    protected $fillable = [
        'lesson_id',
        'type',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the lesson this reminder belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
