<?php

declare(strict_types=1);

namespace App\Actions\Shared\Contact;

use App\Models\Contact;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateContactAction
{
    /**
     * Create a new emergency contact for an instructor or student.
     *
     * @param  Instructor|Student  $contactable  The entity to create a contact for
     * @param  array  $data  Contact data (name, relationship, phone, email, is_primary)
     *
     * @throws InvalidArgumentException If contactable is not Instructor or Student
     */
    public function __invoke(Model $contactable, array $data): Contact
    {
        if (! $contactable instanceof Instructor && ! $contactable instanceof Student) {
            throw new InvalidArgumentException(
                'Contactable must be an instance of Instructor or Student'
            );
        }

        // If setting as primary, unset any existing primary contacts
        if (! empty($data['is_primary'])) {
            $contactable->contacts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        return $contactable->contacts()->create($data);
    }
}
