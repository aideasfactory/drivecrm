<?php

declare(strict_types=1);

namespace App\Actions\Instructor\Mileage;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetMileageLogsAction
{
    public function __invoke(Instructor $instructor): Collection
    {
        return $instructor->mileageLogs()
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();
    }
}
