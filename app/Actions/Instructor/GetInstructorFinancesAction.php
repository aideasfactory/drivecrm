<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorFinancesAction
{
    public function __invoke(Instructor $instructor): Collection
    {
        return $instructor->finances()
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();
    }
}
