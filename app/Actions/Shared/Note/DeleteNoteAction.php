<?php

declare(strict_types=1);

namespace App\Actions\Shared\Note;

use App\Models\Note;

class DeleteNoteAction
{
    /**
     * Soft delete a note.
     *
     * @param  Note  $note  The note to delete
     */
    public function __invoke(Note $note): void
    {
        $note->delete();
    }
}
