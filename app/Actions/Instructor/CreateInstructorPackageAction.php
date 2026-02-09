<?php

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Package;

class CreateInstructorPackageAction
{
    /**
     * Create a new bespoke package for an instructor.
     */
    public function __invoke(Instructor $instructor, array $data): Package
    {
        return Package::create([
            'instructor_id' => $instructor->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'total_price_pence' => $data['total_price_pence'],
            'lessons_count' => $data['lessons_count'],
            'active' => true, // Default to active
        ]);
    }
}
