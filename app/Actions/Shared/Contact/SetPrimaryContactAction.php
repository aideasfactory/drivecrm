<?php

declare(strict_types=1);

namespace App\Actions\Shared\Contact;

use App\Models\Contact;

class SetPrimaryContactAction
{
    /**
     * Set a contact as primary, unsetting all others for the same entity.
     *
     * @param  Contact  $contact  The contact to set as primary
     */
    public function __invoke(Contact $contact): Contact
    {
        // Unset all other primary contacts for this entity
        $contact->contactable->contacts()
            ->where('id', '!=', $contact->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        // Set this contact as primary
        $contact->update(['is_primary' => true]);

        return $contact->fresh();
    }
}
