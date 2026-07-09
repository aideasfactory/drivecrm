<?php

namespace App\Actions\Shared;

use App\Jobs\LogActivityJob;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LogActivityAction
{
    /**
     * Log an activity for an instructor or student.
     *
     * @param  Instructor|Student  $loggable  The entity to log activity for
     * @param  string  $message  Full audit message (self-contained: who, what, to whom)
     * @param  string  $category  Activity category (lesson, booking, message, payment, profile, etc.)
     * @param  array|null  $metadata  Additional context data
     * @param  string|null  $displayMessage  Short user-facing message for the UI; falls back to $message when null
     *
     * @throws InvalidArgumentException If loggable is not Instructor or Student
     */
    public function __invoke(
        Model $loggable,
        string $message,
        string $category,
        ?array $metadata = null,
        ?string $displayMessage = null
    ): void {
        // Validate that loggable is either Instructor or Student
        if (! $loggable instanceof Instructor && ! $loggable instanceof Student) {
            throw new InvalidArgumentException(
                'Loggable must be an instance of Instructor or Student'
            );
        }

        // Dispatch job to queue
        LogActivityJob::dispatch($loggable, $message, $category, $metadata, $displayMessage);
    }
}
