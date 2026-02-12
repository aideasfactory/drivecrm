<?php

declare(strict_types=1);

namespace App\Actions\Shared\Contact;

use App\Models\Contact;

class UpdateContactAction
{
    /**
     * Update an existing emergency contact.
     *
     * @param  Contact  $contact  The contact to update
     * @param  array  $data  Updated contact data
     */
    public function __invoke(Contact $contact, array $data): Contact
    {
        // If setting as primary, unset any existing primary contacts for the same entity
        if (! empty($data['is_primary'])) {
            $contact->contactable->contacts()
                ->where('id', '!=', $contact->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $contact->update($data);

        return $contact->fresh();
    }
}
