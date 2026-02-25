<?php

declare(strict_types=1);

namespace App\Actions\Student\Contact;

use App\Actions\Shared\Contact\CreateContactAction;
use App\Models\Contact;
use App\Models\Student;

class AutoCreateEmergencyContactAction
{
    public function __construct(
        protected CreateContactAction $createContact
    ) {}

    /**
     * Auto-create an emergency contact from the student's contact fields (third-party booker).
     *
     * Only creates if:
     * - Student has contact_first_name populated
     * - Student has no existing emergency contacts
     *
     * @return Contact|null The created contact, or null if conditions not met
     */
    public function __invoke(Student $student): ?Contact
    {
        // Only auto-create if student has no contacts yet
        if ($student->contacts()->exists()) {
            return null;
        }

        // Only create if student has third-party contact details
        if (empty($student->contact_first_name)) {
            return null;
        }

        $name = trim($student->contact_first_name.' '.($student->contact_surname ?? ''));

        $data = [
            'name' => $name,
            'relationship' => 'Parent',
            'phone' => $student->contact_phone ?? '',
            'email' => $student->contact_email ?? '',
            'is_primary' => true,
        ];

        // Only create if we have at least a phone number
        if (empty($data['phone'])) {
            return null;
        }

        return ($this->createContact)($student, $data);
    }
}
