<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\AccountDeletionRequestStatus;
use App\Enums\InstructorStatus;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\Instructor;
use Illuminate\Support\Facades\DB;

class GetInstructorMergeChecksAction
{
    /**
     * HMRC tables keyed by the instructor's login. Tax submissions are tied to that login and must not move.
     *
     * @var array<string, string>
     */
    private const HMRC_USER_TABLES = [
        'hmrc_tokens' => 'HMRC connection',
        'hmrc_itsa_obligations' => 'HMRC ITSA obligations',
        'hmrc_itsa_calculations' => 'HMRC ITSA calculations',
        'hmrc_itsa_supplementary_data' => 'HMRC ITSA supplementary data',
        'hmrc_itsa_final_declarations' => 'HMRC ITSA final declarations',
        'hmrc_vat_obligations' => 'HMRC VAT obligations',
    ];

    /**
     * HMRC tables keyed by the instructor profile.
     *
     * @var array<string, string>
     */
    private const HMRC_INSTRUCTOR_TABLES = [
        'hmrc_itsa_businesses' => 'HMRC businesses',
        'hmrc_itsa_quarterly_updates' => 'HMRC quarterly updates',
        'hmrc_vat_returns' => 'HMRC VAT returns',
    ];

    public function __construct(
        protected MergeInstructorAccountsAction $mergeInstructorAccounts,
    ) {}

    /**
     * Decide whether `$from` can be merged into `$into`.
     *
     * Blockers stop the merge outright: anything that can't be re-pointed safely (a Stripe Connect account
     * on the retired profile, HMRC data, year-end archives, progress with no matching tracker item), an
     * account already retired or scheduled for deletion, or a retired profile the public booking flow
     * routes to, or unsigned lessons that would be stranded on the retired profile because their order
     * already has a payout there. With `$allowRetiredStripeAccount` (CLI `--leave-stripe` only) a Stripe
     * Connect account on the retired profile is allowed and stays there with its payout history and the
     * orders tied to it.
     *
     * Warnings don't stop it but an admin must acknowledge them: diary clashes, pupils who look like the
     * same person on both accounts, unsettled payouts left behind, and a kept account that can't take payouts.
     *
     * @return array{
     *     blockers: list<string>,
     *     warnings: list<array{key: string, message: string}>,
     *     duplicate_pupils: list<array{retired_student_id: int, kept_student_id: int, name: string, matched_on: string}>,
     *     clashes: array<int, array<string, int|string>>,
     * }
     */
    public function __invoke(Instructor $from, Instructor $into, bool $allowRetiredStripeAccount = false): array
    {
        $from->loadMissing('user');
        $into->loadMissing('user');

        if ($from->is($into)) {
            return [
                'blockers' => ['The old and live accounts are the same instructor.'],
                'warnings' => [],
                'duplicate_pupils' => [],
                'clashes' => [],
            ];
        }

        $duplicatePupils = $this->duplicatePupils($from, $into);
        $clashes = $this->mergeInstructorAccounts->clashingItems($from, $into);

        return [
            'blockers' => $this->blockers($from, $into, $allowRetiredStripeAccount),
            'warnings' => $this->warnings($from, $into, count($duplicatePupils), count($clashes)),
            'duplicate_pupils' => $duplicatePupils,
            'clashes' => $clashes,
        ];
    }

    /**
     * @return list<string>
     */
    private function blockers(Instructor $from, Instructor $into, bool $allowRetiredStripeAccount): array
    {
        $blockers = [];

        if ($from->merged_at !== null || $from->status === InstructorStatus::Archived->value) {
            $blockers[] = 'The old account is already archived or merged.';
        }

        if ($into->merged_at !== null || $into->status === InstructorStatus::Archived->value) {
            $blockers[] = 'The live account is archived or was merged into another account. Pick an active account to keep.';
        }

        if ($from->stripe_account_id !== null && ! $allowRetiredStripeAccount) {
            $blockers[] = $from->stripe_account_id === $into->stripe_account_id
                ? "Both accounts hold the same Stripe Connect account ({$from->stripe_account_id}). This tool won't merge accounts that share a Stripe account. Ask the tech team."
                : "The old account has its own Stripe Connect account ({$from->stripe_account_id}). Its payouts went there, and its unpaid lessons would be paid to the live account's Stripe account instead. This tool won't decide that. Ask the tech team.";
        }

        $strandedLessons = $allowRetiredStripeAccount ? 0 : DB::table('lessons')
            ->where('instructor_id', $from->id)
            ->whereIn('status', [LessonStatus::DRAFT->value, LessonStatus::PENDING->value])
            ->whereIn('order_id', DB::table('lessons')
                ->join('payouts', 'payouts.lesson_id', '=', 'lessons.id')
                ->where('lessons.instructor_id', $from->id)
                ->whereNotNull('lessons.order_id')
                ->select('lessons.order_id'))
            ->count();
        if ($strandedLessons > 0) {
            $blockers[] = "{$strandedLessons} lesson(s) not yet signed off belong to orders that already have a payout on the old account. Those orders stay on the old account, so the live account couldn't sign the lessons off. Ask the tech team.";
        }

        if (DB::table('year_end_archives')->where('instructor_id', $from->id)->exists()) {
            $blockers[] = 'The old account has year-end archives.';
        }

        foreach (self::HMRC_USER_TABLES as $table => $label) {
            if (DB::table($table)->where('user_id', $from->user_id)->exists()) {
                $blockers[] = "The old account has {$label}.";
            }
        }

        foreach (self::HMRC_INSTRUCTOR_TABLES as $table => $label) {
            if (DB::table($table)->where('instructor_id', $from->id)->exists()) {
                $blockers[] = "The old account has {$label}.";
            }
        }

        $unmappedProgress = $this->unmappedProgressCount($from, $into);
        if ($unmappedProgress > 0) {
            $blockers[] = "The old account has {$unmappedProgress} pupil progress score(s) on tracker items the live account doesn't have. Add matching items to the live account's progress tracker first.";
        }

        foreach (['old' => $from, 'live' => $into] as $label => $instructor) {
            if (DB::table('account_deletion_requests')->where('user_id', $instructor->user_id)
                ->where('status', AccountDeletionRequestStatus::PENDING->value)->exists()) {
                $blockers[] = "The {$label} account is scheduled for deletion. Cancel the deletion first.";
            }
        }

        $bookingInstructorIds = array_map('intval', array_filter((array) config('booking.instructor_ids', [])));
        if (in_array($from->id, $bookingInstructorIds, true)) {
            $blockers[] = 'The public booking page routes new enquiries to the old account (BOOKING_INSTRUCTOR_*_ID). Point it at another instructor first.';
        }

        return $blockers;
    }

    /**
     * @return list<array{key: string, message: string}>
     */
    private function warnings(Instructor $from, Instructor $into, int $duplicatePupilCount, int $clashCount): array
    {
        $warnings = [];

        if ($clashCount > 0) {
            $warnings[] = ['key' => 'diary_clashes', 'message' => "{$clashCount} diary item(s) on the old account overlap the live account's diary. They will move across as they are and overlap. Resolve them first if you can."];
        }

        if ($duplicatePupilCount > 0) {
            $warnings[] = ['key' => 'duplicate_pupils', 'message' => "{$duplicatePupilCount} pupil(s) look like the same person on both accounts. Both pupil records move to the live account and are not combined."];
        }

        $unsettledPayouts = DB::table('payouts')->where('instructor_id', $from->id)
            ->whereIn('status', [PayoutStatus::PENDING->value, PayoutStatus::FAILED->value])->count();
        if ($unsettledPayouts > 0) {
            $warnings[] = ['key' => 'unsettled_payouts', 'message' => "{$unsettledPayouts} pending or failed payout(s) stay on the old account with their lessons and orders. Settle them from the old account's page."];
        }

        $movingStripeLessons = DB::table('lessons')
            ->join('orders', 'orders.id', '=', 'lessons.order_id')
            ->where('lessons.instructor_id', $from->id)
            ->where('lessons.status', LessonStatus::PENDING->value)
            ->whereNotIn('orders.payment_mode', [PaymentMode::IMPORTED->value, PaymentMode::CASH->value])
            ->count();
        if ($movingStripeLessons > 0 && ! $into->canReceiveTransfers()) {
            $warnings[] = ['key' => 'live_account_cannot_take_payouts', 'message' => "{$movingStripeLessons} lesson(s) moving across will be paid out through Stripe at sign-off, but the live account can't receive Stripe payouts yet. Those sign-offs will fail until it finishes Stripe onboarding."];
        }

        if ($into->status !== InstructorStatus::Active->value) {
            $warnings[] = ['key' => 'live_account_not_active', 'message' => "The live account's status is \"{$into->status}\", not active."];
        }

        return $warnings;
    }

    private function unmappedProgressCount(Instructor $from, Instructor $into): int
    {
        $progressMap = $this->mergeInstructorAccounts->progressSubcategoryMap($from, $into);
        $unmappedSubcategoryIds = array_keys(array_filter($progressMap, fn (?int $keptId): bool => $keptId === null));

        if ($unmappedSubcategoryIds === []) {
            return 0;
        }

        return $this->mergeInstructorAccounts->progressQuery($from)
            ->whereIn('p.progress_subcategory_id', $unmappedSubcategoryIds)
            ->count();
    }

    /**
     * Pupils on the old account that look like a pupil on the live account (same email, phone or full name).
     *
     * @return list<array{retired_student_id: int, kept_student_id: int, name: string, matched_on: string}>
     */
    private function duplicatePupils(Instructor $from, Instructor $into): array
    {
        $columns = ['id', 'first_name', 'surname', 'email', 'phone'];
        $kept = DB::table('students')->where('instructor_id', $into->id)->whereNull('deleted_at')->get($columns);

        if ($kept->isEmpty()) {
            return [];
        }

        $index = [];
        foreach ($kept as $student) {
            foreach ($this->matchKeys($student) as $key) {
                $index[$key] ??= (int) $student->id;
            }
        }

        $duplicates = [];
        foreach (DB::table('students')->where('instructor_id', $from->id)->whereNull('deleted_at')->get($columns) as $student) {
            foreach ($this->matchKeys($student) as $key) {
                if (isset($index[$key])) {
                    $duplicates[] = [
                        'retired_student_id' => (int) $student->id,
                        'kept_student_id' => $index[$key],
                        'name' => trim("{$student->first_name} {$student->surname}"),
                        'matched_on' => strstr($key, ':', true),
                    ];

                    break;
                }
            }
        }

        return $duplicates;
    }

    /**
     * @return list<string>
     */
    private function matchKeys(object $student): array
    {
        $keys = [];
        $email = mb_strtolower(trim((string) $student->email));
        $phone = preg_replace('/\D+/', '', (string) $student->phone);
        $name = mb_strtolower(trim(preg_replace('/\s+/', ' ', "{$student->first_name} {$student->surname}")));

        if ($email !== '') {
            $keys[] = "email:{$email}";
        }
        if (strlen($phone) >= 7) {
            $keys[] = 'phone:'.substr($phone, -10);
        }
        if ($name !== '' && str_contains($name, ' ')) {
            $keys[] = "name:{$name}";
        }

        return $keys;
    }
}
