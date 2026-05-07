<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class GetTransferableStudentsAction
{
    /**
     * Return students eligible to be transferred (those who currently have an instructor),
     * with their current instructor's name eager-loaded for display in the dropdown.
     *
     * @return Collection<int, Student>
     */
    public function __invoke(): Collection
    {
        return Student::query()
            ->whereNotNull('instructor_id')
            ->with('instructor.user:id,name')
            ->orderBy('first_name')
            ->orderBy('surname')
            ->get();
    }
}
