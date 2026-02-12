<?php

declare(strict_types=1);

namespace App\Actions\Shared\Contact;

use App\Models\Contact;

class DeleteContactAction
{
    /**
     * Delete an emergency contact.
     *
     * @param  Contact  $contact  The contact to delete
     */
    public function __invoke(Contact $contact): void
    {
        $contact->delete();
    }
}
