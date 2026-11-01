<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Student\SetStudentCashPaymentsAction;
use App\Models\ImportMapping;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

class FixImportedPupilsAction
{
    public function __construct(
        protected SetStudentCashPaymentsAction $setStudentCashPayments,
    ) {}

    /**
     * One-off clean-up of pupils the importer created: alternative payments
     * are turned on (logged like the owner switch). Pupils already on Drive+
     * are never touched; belt and braces, the pupil's user must also have
     * `imported_at` set. Nothing else on the pupil is changed (phone numbers
     * are left as they are).
     *
     * `$limit` stops after that many pupils needing the change, so a re-run
     * picks up the next batch. Pupils already switched on don't count.
     *
     * `examples` holds the changes (who, field, before → after): up to 20, or
     * every change when a limit is given.
     *
     * @return array{pupils: int, alternative_payments_turned_on: int, examples: array<int, array{0: string, 1: string, 2: string, 3: string}>}
     */
    public function __invoke(bool $dryRun = false, ?User $changedBy = null, ?int $limit = null): array
    {
        $examples = [];
        $maxExamples = $limit === null ? 20 : PHP_INT_MAX;

        $counts = [
            'pupils' => 0,
            'alternative_payments_turned_on' => 0,
        ];

        foreach ($this->createdStudentIds()->chunk(500) as $ids) {
            foreach (Student::query()->whereKey($ids->all())->whereHas('user', fn ($query) => $query->whereNotNull('imported_at'))->orderBy('id')->get() as $student) {
                $counts['pupils']++;

                if ($student->cash_payments_enabled) {
                    continue;
                }

                if ($limit !== null && $counts['alternative_payments_turned_on'] >= $limit) {
                    break 2;
                }

                $counts['alternative_payments_turned_on']++;

                if (count($examples) < $maxExamples) {
                    $examples[] = ["Pupil {$student->id} {$student->first_name} {$student->surname}", 'alternative payments', 'off', 'on'];
                }

                if (! $dryRun) {
                    ($this->setStudentCashPayments)($student, true, $changedBy);
                }
            }
        }

        return $counts + ['examples' => $examples];
    }

    /**
     * @return Collection<int, int>
     */
    protected function createdStudentIds(): Collection
    {
        return ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_STUDENT)
            ->where('action', ImportMapping::ACTION_CREATED)
            ->orderBy('model_id')
            ->pluck('model_id');
    }
}
