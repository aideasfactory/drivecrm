<?php

declare(strict_types=1);

namespace App\Actions\Shared\Note;

use App\Actions\Shared\LogActivityAction;
use App\Models\Note;

class UpdateNoteAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Update an existing note.
     *
     * @param  Note  $note  The note to update
     * @param  string  $content  The new note content
     */
    public function __invoke(Note $note, string $content): Note
    {
        $note->update([
            'note' => $content,
        ]);

        ($this->logActivity)($note->noteable, 'Note updated: '.$content, 'note');

        return $note->refresh();
    }
}
