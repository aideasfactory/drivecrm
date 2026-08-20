<?php

declare(strict_types=1);

namespace App\Actions\Shared\Note;

use App\Actions\Shared\LogActivityAction;
use App\Models\Instructor;
use App\Models\Note;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateNoteAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Create a new note for an instructor or student.
     *
     * @param  Instructor|Student  $noteable  The entity to create a note for
     * @param  string  $note  The note content
     * @param  bool  $isInternal  Admin-only internal note (skips the activity log,
     *                            which is visible to instructors and would leak the content)
     *
     * @throws InvalidArgumentException If noteable is not Instructor or Student
     */
    public function __invoke(Model $noteable, string $note, bool $isInternal = false): Note
    {
        if (! $noteable instanceof Instructor && ! $noteable instanceof Student) {
            throw new InvalidArgumentException(
                'Noteable must be an instance of Instructor or Student'
            );
        }

        $created = $noteable->notes()->create([
            'note' => $note,
            'is_internal' => $isInternal,
            'user_id' => auth()->id(),
        ]);

        $created->load('user:id,name');

        if (! $isInternal) {
            ($this->logActivity)($noteable, 'Note added: '.$note, 'note');
        }

        return $created;
    }
}
