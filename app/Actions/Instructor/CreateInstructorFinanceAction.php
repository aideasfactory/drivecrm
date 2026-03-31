<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\InstructorFinance;

class CreateInstructorFinanceAction
{
    public function __invoke(Instructor $instructor, array $data): InstructorFinance
    {
        return $instructor->finances()->create([
            'type' => $data['type'],
            'description' => $data['description'],
            'amount_pence' => $data['amount_pence'],
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
