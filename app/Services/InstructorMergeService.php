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
     * Merge the instructor account logged in with `$fromEmail` into the one logged in with `$intoEmail`.
     *
     * Refuses (RuntimeException) when the old account holds anything that must not be moved by a script:
     * a Stripe Connect account, payouts, HMRC data or pupil progress on its progress tracker.
     *
     * @return array{from: Instructor, into: Instructor, report: array<string, mixed>}
     */
    public function merge(string $fromEmail, string $intoEmail, bool $dryRun): array
    {
        $from = $this->findInstructor($fromEmail);
        $into = $this->findInstructor($intoEmail);

        if ($from->is($into)) {
            throw new RuntimeException('Both emails belong to the same instructor.');
        }

        $this->guardNothingUnmovable($from);

        $report = ($this->mergeInstructorAccounts)($from, $into, $dryRun);

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
     * Stop if the old account holds money or tax records — those need a person, not a script.
     */
    private function guardNothingUnmovable(Instructor $from): void
    {
        $blockers = array_filter([
            'a Stripe Connect account' => $from->stripe_account_id !== null,
            'payouts' => DB::table('payouts')->where('instructor_id', $from->id)->exists(),
            'year-end archives' => DB::table('year_end_archives')->where('instructor_id', $from->id)->exists(),
            'HMRC connection' => DB::table('hmrc_tokens')->where('user_id', $from->user_id)->exists(),
            'HMRC businesses' => DB::table('hmrc_itsa_businesses')->where('instructor_id', $from->id)->exists(),
            'HMRC quarterly updates' => DB::table('hmrc_itsa_quarterly_updates')->where('instructor_id', $from->id)->exists(),
            'HMRC VAT returns' => DB::table('hmrc_vat_returns')->where('instructor_id', $from->id)->exists(),
            'pupil progress on its progress tracker' => DB::table('student_progress')
                ->join('progress_subcategories', 'progress_subcategories.id', '=', 'student_progress.progress_subcategory_id')
                ->join('progress_categories', 'progress_categories.id', '=', 'progress_subcategories.progress_category_id')
                ->where('progress_categories.instructor_id', $from->id)
                ->exists(),
        ]);

        if ($blockers !== []) {
            throw new RuntimeException(sprintf(
                'Refusing to merge: %s has %s.',
                $from->user->email,
                implode(', ', array_keys($blockers)),
            ));
        }
    }
}
