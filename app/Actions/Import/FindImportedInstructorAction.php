<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\Instructor;
use App\Support\ImportValues;

class FindImportedInstructorAction
{
    /**
     * The Drive instructor for a legacy instructor ref: imported or linked
     * earlier (import_mappings), or an existing Drive+ instructor written as
     * `DRIVE-<id>`. Null when neither.
     */
    public function __invoke(string $instructorRef): ?Instructor
    {
        $id = ImportMapping::modelIdFor(ImportMapping::ENTITY_INSTRUCTOR, $instructorRef)
            ?? ImportValues::driveInstructorId($instructorRef);

        return $id !== null ? Instructor::query()->find($id) : null;
    }
}
