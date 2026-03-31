<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\InstructorFinance;

class UpdateInstructorFinanceAction
{
    public function __invoke(InstructorFinance $finance, array $data): InstructorFinance
    {
        $finance->update($data);

        return $finance->fresh();
    }
}
