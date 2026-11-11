<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\MergeInstructorAccountsAction;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstructorMergeService extends BaseService
{
    public function __construct(
        protected MergeInstructorAccountsAction $mergeInstructorAccounts,
    ) {}

    /**
     * Merge the instructor account logged in with `$fromEmail` into the one logged in with `$intoEmail`,
     * then retire the `$fromEmail` account. With `$takeEmail` the kept account takes `$fromEmail` as its login.
     *
     * Refuses (RuntimeException) when the retired account holds anything a script must not decide:
     * a Stripe Connect account (unless `$leaveStripe`), HMRC data, year-end archives, or pupil progress
     * on a tracker subcategory the kept account doesn't have.
     *
     * @return array{from: Instructor, into: Instructor, report: array<string, mixed>}
     */
    public function merge(string $fromEmail, string $intoEmail, bool $takeEmail, bool $leaveStripe, bool $dryRun): array
    {
        $from = $this->findInstructor($fromEmail);
        $into = $this->findInstructor($intoEmail);

        if ($from->is($into)) {
            throw new RuntimeException('Both emails belong to the same instructor.');
        }

        $this->guardNothingUnmovable($from, $into, $leaveStripe);

        $report = ($this->mergeInstructorAccounts)($from, $into, $takeEmail, $dryRun);

        if (! $dryRun) {
            foreach ([$from->id, $into->id] as $instructorId) {
                $this->invalidate(array_map(
                    fn (string $suffix): string => $this->cacheKey('instructor', $instructorId, $suffix),
                    ['grouped_students', 'packages', 'vehicles', 'year_end_archives'],
                ));
            }
        }

        return ['from' => $from, 'into' => $into, 'report' => $report];
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
     * Stop if the retired account holds tax records, an unexpected Stripe account, or progress we can't map.
     */
    private function guardNothingUnmovable(Instructor $from, Instructor $into, bool $leaveStripe): void
    {
        $unmappedProgress = 0;
        $progressMap = $this->mergeInstructorAccounts->progressSubcategoryMap($from, $into);
        $unmappedSubcategoryIds = array_keys(array_filter($progressMap, fn (?int $keptId): bool => $keptId === null));

        if ($unmappedSubcategoryIds !== []) {
            $unmappedProgress = $this->mergeInstructorAccounts->progressQuery($from)
                ->whereIn('p.progress_subcategory_id', $unmappedSubcategoryIds)
                ->count();
        }

        $blockers = array_filter([
            "a Stripe Connect account ({$from->stripe_account_id}) — pass --leave-stripe to keep it on the archived profile" => $from->stripe_account_id !== null && ! $leaveStripe,
            'year-end archives' => DB::table('year_end_archives')->where('instructor_id', $from->id)->exists(),
            'HMRC connection' => DB::table('hmrc_tokens')->where('user_id', $from->user_id)->exists(),
            'HMRC businesses' => DB::table('hmrc_itsa_businesses')->where('instructor_id', $from->id)->exists(),
            'HMRC quarterly updates' => DB::table('hmrc_itsa_quarterly_updates')->where('instructor_id', $from->id)->exists(),
            'HMRC VAT returns' => DB::table('hmrc_vat_returns')->where('instructor_id', $from->id)->exists(),
            "{$unmappedProgress} pupil progress score(s) on tracker items the kept account doesn't have" => $unmappedProgress > 0,
        ]);

        if ($blockers !== []) {
            throw new RuntimeException(sprintf(
                'Refusing to merge: %s has %s.',
                $from->user->email,
                implode('; ', array_keys($blockers)),
            ));
        }
    }
}
