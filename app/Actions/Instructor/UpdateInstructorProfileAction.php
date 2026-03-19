<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;

class UpdateInstructorProfileAction
{
    /**
     * Update the instructor's profile with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Instructor $instructor, array $data): Instructor
    {
        $instructor->update($data);

        return $instructor->fresh();
    }
}
