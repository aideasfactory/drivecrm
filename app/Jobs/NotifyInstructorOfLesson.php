<?php

namespace App\Jobs;

use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyInstructorOfLesson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Lesson $lesson
    ) {}

    public function handle(): void
    {
        // TODO: Implement actual notification (email, SMS, etc.)
        Log::info('Instructor notification for lesson', [
            'lesson_id' => $this->lesson->id,
            'instructor_id' => $this->lesson->instructor_id,
            'date' => $this->lesson->date,
            'start_time' => $this->lesson->start_time,
        ]);
    }
}
