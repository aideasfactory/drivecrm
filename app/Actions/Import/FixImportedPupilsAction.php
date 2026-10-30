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
     * and linked instructors are never touched; belt and braces, the
     * user must also have `imported_at` set):
     * - pupils get alternative payments turned on (logged like the owner switch);
     * - UK phone numbers are normalised to 07… with no spaces (lost leading 0,
     *   +44/44 prefix, spaces), on pupils (phone + contact phone) and instructors.
     *
     * `$limit` stops after that many records needing a change (pupils first,
     * then instructors), so a re-run picks up the next batch. Records already
     * correct don't count.
     *
     * `examples` holds the changes (who, field, before → after): up to 20, or
     * every change when a limit is given.
     *
     * @return array{pupils: int, alternative_payments_turned_on: int, pupil_phones_fixed: int, instructors: int, instructor_phones_fixed: int, examples: array<int, array{0: string, 1: string, 2: string, 3: string}>}
     */
    public function __invoke(bool $dryRun = false, ?User $changedBy = null, ?int $limit = null): array
    {
        $examples = [];
        $maxExamples = $limit === null ? 20 : PHP_INT_MAX;
        $addExample = function (string $who, string $field, ?string $before, ?string $after) use (&$examples, $maxExamples): void {
            if ($before !== $after && count($examples) < $maxExamples) {
                $examples[] = [$who, $field, (string) $before, (string) $after];
            }
        };

        $changed = 0;

        $counts = [
            'pupils' => 0,
            'alternative_payments_turned_on' => 0,
            'pupil_phones_fixed' => 0,
            'instructors' => 0,
            'instructor_phones_fixed' => 0,
        ];

        foreach ($this->createdIds(ImportMapping::ENTITY_STUDENT)->chunk(500) as $ids) {
            foreach (Student::query()->whereKey($ids->all())->whereHas('user', fn ($query) => $query->whereNotNull('imported_at'))->orderBy('id')->get() as $student) {
                $counts['pupils']++;

                $phones = [
                    'phone' => ImportValues::ukPhone($student->phone),
                    'contact_phone' => ImportValues::ukPhone($student->contact_phone),
                ];
                $phonesChanged = $phones['phone'] !== $student->phone || $phones['contact_phone'] !== $student->contact_phone;

                if (! $phonesChanged && $student->cash_payments_enabled) {
                    continue;
                }

                if ($limit !== null && $changed >= $limit) {
                    break 2;
                }

                $changed++;
                $who = "Pupil {$student->id} {$student->first_name} {$student->surname}";

                if ($phonesChanged) {
                    $counts['pupil_phones_fixed']++;
                    $addExample($who, 'phone', $student->phone, $phones['phone']);
                    $addExample($who, 'contact_phone', $student->contact_phone, $phones['contact_phone']);

                    if (! $dryRun) {
                        $student->forceFill($phones)->save();
                    }
                }

                if (! $student->cash_payments_enabled) {
                    $counts['alternative_payments_turned_on']++;
                    $addExample($who, 'alternative payments', 'off', 'on');

                    if (! $dryRun) {
                        ($this->setStudentCashPayments)($student, true, $changedBy);
                    }
                }
            }
        }

        foreach ($this->createdIds(ImportMapping::ENTITY_INSTRUCTOR)->chunk(500) as $ids) {
            foreach (Instructor::query()->whereKey($ids->all())->whereHas('user', fn ($query) => $query->whereNotNull('imported_at'))->orderBy('id')->get() as $instructor) {
                $counts['instructors']++;

                $phone = ImportValues::ukPhone($instructor->phone);

                if ($phone === $instructor->phone) {
                    continue;
                }

                if ($limit !== null && $changed >= $limit) {
                    break 2;
                }

                $changed++;
                $counts['instructor_phones_fixed']++;
                $addExample("Instructor {$instructor->id}", 'phone', $instructor->phone, $phone);

                if (! $dryRun) {
                    $instructor->forceFill(['phone' => $phone])->save();
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
            ->orderBy('model_id')
            ->pluck('model_id');
    }
}
