<?php

declare(strict_types=1);

namespace App\Actions\Instructor\Mileage;

use App\Models\MileageLog;

class DeleteMileageLogAction
{
    public function __invoke(MileageLog $log): bool
    {
        return (bool) $log->delete();
    }
}
