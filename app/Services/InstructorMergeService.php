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
     * Refuses (RuntimeException) when GetInstructorMergeChecksAction finds a blocker, checked again under the
     * merge's row locks. `$leaveStripe` allows a Stripe Connect account on the retired profile (it stays there
     * with its payout history).
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
            : $this->runMerge($from, $into, $takeEmail, null, function (Instructor $lockedFrom, Instructor $lockedInto) use ($leaveStripe): void {
                $this->guardNoBlockers($lockedFrom, $lockedInto, $leaveStripe);
            });

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
     *     warnings_token: string,
     * }
     */
    public function preview(Instructor $from, Instructor $into, bool $takeEmail = false): array
    {
        $checks = ($this->getInstructorMergeChecks)($from, $into);

        return [
            'report' => $from->is($into) ? null : ($this->mergeInstructorAccounts)($from, $into, $takeEmail, true),
            'warnings_token' => $this->warningsToken($checks['warnings']),
        ] + $checks;
    }

    /**
     * Merge `$from` into `$into` on behalf of an owner. The checks run again inside the merge transaction
     * against the locked, reloaded accounts: any blocker, or warnings other than the ones the owner
     * acknowledged (`$acknowledgedWarningsToken` from the preview), throws and rolls back.
     *
     * @return array<string, mixed> the merge report
     *
     * @throws RuntimeException
     */
    public function mergeInstructors(Instructor $from, Instructor $into, User $mergedBy, bool $takeEmail, ?string $acknowledgedWarningsToken): array
    {
        $guard = function (Instructor $lockedFrom, Instructor $lockedInto) use ($acknowledgedWarningsToken): void {
            $checks = $this->guardNoBlockers($lockedFrom, $lockedInto, false);

            if ($checks['warnings'] !== [] && ! hash_equals($this->warningsToken($checks['warnings']), (string) $acknowledgedWarningsToken)) {
                throw new RuntimeException('The warnings for this merge have changed or were not acknowledged. Review them, tick the acknowledgement and confirm again.');
            }
        };

        $guard($from, $into);

        return $this->runMerge($from, $into, $takeEmail, $mergedBy, $guard);
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
     * Identifies the set of warnings an owner saw, so a warning that appears after the preview needs a fresh tick.
     *
     * @param  list<array{key: string, message: string}>  $warnings
     */
    private function warningsToken(array $warnings): string
    {
        $keys = array_column($warnings, 'key');
        sort($keys);

        return hash('sha256', implode('|', $keys));
    }

    /**
     * @param  callable(Instructor, Instructor): void  $guardLocked
     * @return array<string, mixed>
     */
    private function runMerge(Instructor $from, Instructor $into, bool $takeEmail, ?User $mergedBy, callable $guardLocked): array
    {
        $report = ($this->mergeInstructorAccounts)($from, $into, $takeEmail, false, $mergedBy, $guardLocked);

        $keptAccountStudentIds = DB::table('students')->where('instructor_id', $into->id)->pluck('id')->all();

        $keys = [];
        foreach ([$from->id, $into->id] as $instructorId) {
            foreach (['grouped_students', 'packages', 'vehicles', 'year_end_archives'] as $suffix) {
                $keys[] = $this->cacheKey('instructor', $instructorId, $suffix);
            }
        }
        foreach ([$from->user_id, $into->user_id] as $userId) {
            $keys[] = $this->cacheKey('user', $userId, 'watched_resource_ids');
        }
        foreach ($keptAccountStudentIds as $studentId) {
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
