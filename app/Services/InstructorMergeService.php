<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\GetInstructorMergeChecksAction;
use App\Actions\Instructor\GetInstructorMergeHistoryAction;
use App\Actions\Instructor\MergeInstructorAccountsAction;
use App\Actions\Instructor\SearchMergeableInstructorsAction;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstructorMergeService extends BaseService
{
    public function __construct(
        protected MergeInstructorAccountsAction $mergeInstructorAccounts,
        protected GetInstructorMergeChecksAction $getInstructorMergeChecks,
        protected SearchMergeableInstructorsAction $searchMergeableInstructors,
        protected GetInstructorMergeHistoryAction $getInstructorMergeHistory,
    ) {}

    /**
     * Merge the instructor account logged in with `$fromEmail` into the one logged in with `$intoEmail`,
     * then retire the `$fromEmail` account. With `$takeEmail` the kept account takes `$fromEmail` as its login.
     *
     * Refuses (RuntimeException) when GetInstructorMergeChecksAction finds a blocker. `$leaveStripe` allows a
     * Stripe Connect account on the retired profile (it stays there with its payout history).
     *
     * @return array{from: Instructor, into: Instructor, report: array<string, mixed>}
     */
    public function merge(string $fromEmail, string $intoEmail, bool $takeEmail, bool $leaveStripe, bool $dryRun): array
    {
        $from = $this->findInstructor($fromEmail);
        $into = $this->findInstructor($intoEmail);

        $this->guardNoBlockers($from, $into, $leaveStripe);

        $report = $dryRun
            ? ($this->mergeInstructorAccounts)($from, $into, $takeEmail, true)
            : $this->runMerge($from, $into, $takeEmail, null);

        return ['from' => $from, 'into' => $into, 'report' => $report];
    }

    /**
     * What merging `$from` into `$into` would do: the dry-run counts, blockers, warnings to acknowledge,
     * likely duplicate pupils and diary clashes. Nothing is written.
     *
     * @return array{
     *     report: array<string, mixed>|null,
     *     blockers: list<string>,
     *     warnings: list<array{key: string, message: string}>,
     *     duplicate_pupils: list<array{retired_student_id: int, kept_student_id: int, name: string, matched_on: string}>,
     *     clashes: array<int, array<string, int|string>>,
     * }
     */
    public function preview(Instructor $from, Instructor $into, bool $takeEmail = false): array
    {
        $checks = ($this->getInstructorMergeChecks)($from, $into);

        return [
            'report' => $from->is($into) ? null : ($this->mergeInstructorAccounts)($from, $into, $takeEmail, true),
        ] + $checks;
    }

    /**
     * Merge `$from` into `$into` on behalf of an owner. Re-runs the checks at confirm time: refuses
     * (RuntimeException) on any blocker, or when warnings exist and the owner has not acknowledged them.
     *
     * @return array<string, mixed> the merge report
     */
    public function mergeInstructors(Instructor $from, Instructor $into, User $mergedBy, bool $takeEmail, bool $warningsAcknowledged): array
    {
        $checks = $this->guardNoBlockers($from, $into, false);

        if ($checks['warnings'] !== [] && ! $warningsAcknowledged) {
            throw new RuntimeException('This merge has warnings. Review them, tick the acknowledgement and confirm again.');
        }

        return $this->runMerge($from, $into, $takeEmail, $mergedBy);
    }

    /**
     * @return Collection<int, Instructor>
     */
    public function searchInstructors(?string $search, int $limit): Collection
    {
        return ($this->searchMergeableInstructors)($search, $limit);
    }

    /**
     * @return Collection<int, Instructor>
     */
    public function getMergeHistory(int $limit = 20): Collection
    {
        return ($this->getInstructorMergeHistory)($limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function runMerge(Instructor $from, Instructor $into, bool $takeEmail, ?User $mergedBy): array
    {
        $movingStudentIds = DB::table('students')->where('instructor_id', $from->id)->pluck('id')->all();

        $report = ($this->mergeInstructorAccounts)($from, $into, $takeEmail, false, $mergedBy);

        $keys = [];
        foreach ([$from->id, $into->id] as $instructorId) {
            foreach (['grouped_students', 'packages', 'vehicles', 'year_end_archives'] as $suffix) {
                $keys[] = $this->cacheKey('instructor', $instructorId, $suffix);
            }
        }
        foreach ($movingStudentIds as $studentId) {
            $keys[] = $this->cacheKey('student', $studentId, 'instructor_profile');
            $keys[] = $this->cacheKey('student', $studentId, 'dashboard');
        }
        $this->invalidate($keys);

        return $report;
    }

    private function findInstructor(string $email): Instructor
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException("No user with email {$email}.");
        }

        $instructor = Instructor::query()->where('user_id', $user->id)->first();

        if (! $instructor) {
            throw new RuntimeException("{$email} has no instructor profile.");
        }

        return $instructor->setRelation('user', $user);
    }

    /**
     * @return array{blockers: list<string>, warnings: list<array{key: string, message: string}>, duplicate_pupils: array<int, mixed>, clashes: array<int, mixed>}
     */
    private function guardNoBlockers(Instructor $from, Instructor $into, bool $leaveStripe): array
    {
        $checks = ($this->getInstructorMergeChecks)($from, $into, $leaveStripe);

        if ($checks['blockers'] !== []) {
            throw new RuntimeException('Refusing to merge: '.implode(' ', $checks['blockers']));
        }

        return $checks;
    }
}
