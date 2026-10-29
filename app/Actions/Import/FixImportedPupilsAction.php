<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Student\SetStudentCashPaymentsAction;
use App\Models\ImportMapping;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use App\Support\ImportValues;
use Illuminate\Support\Collection;

class FixImportedPupilsAction
{
    public function __construct(
        protected SetStudentCashPaymentsAction $setStudentCashPayments,
    ) {}

    /**
     * One-off clean-up of rows the importer created (pupils already on Drive+
     * and linked instructors are never touched):
     * - pupils get alternative payments turned on (logged like the owner switch);
     * - UK phone numbers that lost their leading 0 in the CSV are repaired, on
     *   pupils (phone + contact phone) and instructors.
     *
     * `examples` holds up to 20 phone changes (who, field, before → after) for checking a dry run.
     *
     * @return array{pupils: int, alternative_payments_turned_on: int, pupil_phones_fixed: int, instructors: int, instructor_phones_fixed: int, examples: array<int, array{0: string, 1: string, 2: string, 3: string}>}
     */
    public function __invoke(bool $dryRun = false, ?User $changedBy = null): array
    {
        $examples = [];
        $addExample = function (string $who, string $field, ?string $before, ?string $after) use (&$examples): void {
            if ($before !== $after && count($examples) < 20) {
                $examples[] = [$who, $field, (string) $before, (string) $after];
            }
        };

        $counts = [
            'pupils' => 0,
            'alternative_payments_turned_on' => 0,
            'pupil_phones_fixed' => 0,
            'instructors' => 0,
            'instructor_phones_fixed' => 0,
        ];

        foreach ($this->createdIds(ImportMapping::ENTITY_STUDENT)->chunk(500) as $ids) {
            foreach (Student::query()->whereKey($ids->all())->get() as $student) {
                $counts['pupils']++;

                $phones = [
                    'phone' => ImportValues::ukPhone($student->phone),
                    'contact_phone' => ImportValues::ukPhone($student->contact_phone),
                ];

                if ($phones['phone'] !== $student->phone || $phones['contact_phone'] !== $student->contact_phone) {
                    $counts['pupil_phones_fixed']++;
                    $addExample("Pupil {$student->id} {$student->first_name} {$student->surname}", 'phone', $student->phone, $phones['phone']);
                    $addExample("Pupil {$student->id} {$student->first_name} {$student->surname}", 'contact_phone', $student->contact_phone, $phones['contact_phone']);

                    if (! $dryRun) {
                        $student->forceFill($phones)->save();
                    }
                }

                if (! $student->cash_payments_enabled) {
                    $counts['alternative_payments_turned_on']++;

                    if (! $dryRun) {
                        ($this->setStudentCashPayments)($student, true, $changedBy);
                    }
                }
            }
        }

        foreach ($this->createdIds(ImportMapping::ENTITY_INSTRUCTOR)->chunk(500) as $ids) {
            foreach (Instructor::query()->whereKey($ids->all())->get() as $instructor) {
                $counts['instructors']++;

                $phone = ImportValues::ukPhone($instructor->phone);

                if ($phone !== $instructor->phone) {
                    $counts['instructor_phones_fixed']++;
                    $addExample("Instructor {$instructor->id}", 'phone', $instructor->phone, $phone);

                    if (! $dryRun) {
                        $instructor->forceFill(['phone' => $phone])->save();
                    }
                }
            }
        }

        return $counts + ['examples' => $examples];
    }

    /**
     * @return Collection<int, int>
     */
    protected function createdIds(string $entity): Collection
    {
        return ImportMapping::query()
            ->where('entity', $entity)
            ->where('action', ImportMapping::ACTION_CREATED)
            ->pluck('model_id');
    }
}
