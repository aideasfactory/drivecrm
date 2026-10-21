<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConfirmProvisionalLessonAction
{
    /**
     * Keep a provisional lesson where it is and clear the flag: the clash has
     * gone away, or the DRIVE team agreed with everyone to keep it.
     *
     * @throws ValidationException When the lesson is not provisional
     */
    public function __invoke(Lesson $lesson, User $confirmedBy): Lesson
    {
        if (! $lesson->isProvisional()) {
            throw ValidationException::withMessages([
                'lesson' => 'This lesson is no longer provisional.',
            ]);
        }

        $lesson->update(['provisional_at' => null]);

        Log::info('Provisional lesson confirmed', [
            'lesson_id' => $lesson->id,
            'order_id' => $lesson->order_id,
            'confirmed_by_user_id' => $confirmedBy->id,
        ]);

        return $lesson;
    }
}
