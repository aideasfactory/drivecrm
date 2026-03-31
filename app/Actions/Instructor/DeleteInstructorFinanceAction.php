<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\InstructorFinance;

class DeleteInstructorFinanceAction
{
    public function __invoke(InstructorFinance $finance): bool
    {
        return $finance->delete();
    }
}
