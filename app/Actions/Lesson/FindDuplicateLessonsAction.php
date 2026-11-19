<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Models\ActivityLog;
use App\Models\ImportMapping;
use App\Models\InstructorFinance;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only scan of every instructor's diary for duplicated lessons
 * (lessons:find-duplicates). Changes nothing.
 *
 * Lessons are grouped when any of these hold (overlapping groups are merged):
 *
 * - same_goroadie_id: two or more lessons carry the same GoRoadie booking ID
 *   (diary_ref) — via its `lesson` import mapping, or by sitting on the diary
 *   item its `diary` mapping points at. Refs are compared trimmed and
 *   case-insensitively.
 * - exact_duplicate: same instructor, pupil, date and start time.
 * - moved_in_drive_plus: a lesson was moved in Drive Plus (reschedule activity
 *   log on the pupil) away from a date and start time where an importer-created
 *   lesson for the same pupil and instructor now sits — the GoRoadie original
 *   brought back by a later import.
 *
 * Only non-cancelled lessons are considered. Per group, the lesson to KEEP is
 * the Drive Plus version (moved, signed off with an instructor summary, or
 * booked in Drive rather than imported); otherwise the one with linked
 * payments; otherwise the oldest. Every other lesson is REMOVE. A group is
 * flagged for manual review whenever removing automatically could touch money
 * or the choice is unclear.
 */
class FindDuplicateLessonsAction
{
    public const DECISION_KEEP = 'KEEP';

    public const DECISION_REMOVE = 'REMOVE';

    public const TYPE_GOROADIE_ID = 'same_goroadie_id';

    public const TYPE_EXACT = 'exact_duplicate';

    public const TYPE_MOVED = 'moved_in_drive_plus';

    /**
     * @return array{groups: array<int, array{group_id: string, types: array<int, string>, keep_lesson_id: int, needs_review: bool, review_reasons: array<int, string>, rows: array<int, array<string, string|int|null>>}>, counts: array<string, int>}
     */
    public function __invoke(): array
    {
        $candidates = [
            ...$this->sameGoRoadieIdCandidates(),
            ...$this->exactDuplicateCandidates(),
            ...$this->movedInDrivePlusCandidates(),
        ];

        $merged = $this->mergeCandidates($candidates);
        $groups = [];

        foreach (array_chunk($merged, 200) as $chunk) {
            $lessonIds = array_merge(...array_map(fn (array $group): array => $group['lesson_ids'], $chunk));
            $context = $this->loadContext($lessonIds);

            foreach ($chunk as $group) {
                $built = $this->buildGroup($group, $context);

                if ($built !== null) {
                    $groups[] = $built;
                }
            }
        }

        usort($groups, fn (array $a, array $b): int => [$a['rows'][0]['instructor_id'], $a['rows'][0]['date'], $a['group_id']]
            <=> [$b['rows'][0]['instructor_id'], $b['rows'][0]['date'], $b['group_id']]);

        $counts = [
            'groups' => count($groups),
            'groups_needing_review' => count(array_filter($groups, fn (array $group): bool => $group['needs_review'])),
            'lessons_to_keep' => count($groups),
            'lessons_to_remove' => 0,
            'lessons_to_remove_after_review' => 0,
            'instructors' => count(array_unique(array_map(fn (array $group) => $group['rows'][0]['instructor_id'], $groups))),
        ];

        foreach (self::types() as $type) {
            $counts["groups_{$type}"] = count(array_filter($groups, fn (array $group): bool => in_array($type, $group['types'], true)));
        }

        foreach ($groups as $group) {
            $removeCount = count(array_filter($group['rows'], fn (array $row): bool => $row['decision'] === self::DECISION_REMOVE));
            $counts[$group['needs_review'] ? 'lessons_to_remove_after_review' : 'lessons_to_remove'] += $removeCount;
        }

        return ['groups' => $groups, 'counts' => $counts];
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [self::TYPE_GOROADIE_ID, self::TYPE_EXACT, self::TYPE_MOVED];
    }

    /**
     * @return array<int, array{lesson_ids: array<int, int>, type: string, evidence: array<int, string>}>
     */
    protected function sameGoRoadieIdCandidates(): array
    {
        /** @var array<string, array<int, true>> $lessonsByRef */
        $lessonsByRef = [];

        DB::table('import_mappings')
            ->where('entity', ImportMapping::ENTITY_LESSON)
            ->select(['id', 'source_ref', 'model_id'])
            ->orderBy('id')
            ->lazy(5000)
            ->each(function (object $mapping) use (&$lessonsByRef): void {
                $lessonsByRef[self::normaliseRef((string) $mapping->source_ref)][(int) $mapping->model_id] = true;
            });

        DB::table('import_mappings')
            ->join('lessons', 'lessons.calendar_item_id', '=', 'import_mappings.model_id')
            ->where('import_mappings.entity', ImportMapping::ENTITY_DIARY)
            ->select(['import_mappings.id', 'import_mappings.source_ref', 'lessons.id as lesson_id'])
            ->orderBy('import_mappings.id')
            ->orderBy('lessons.id')
            ->lazy(5000)
            ->each(function (object $mapping) use (&$lessonsByRef): void {
                $lessonsByRef[self::normaliseRef((string) $mapping->source_ref)][(int) $mapping->lesson_id] = true;
            });

        $candidates = [];

        foreach ($lessonsByRef as $ref => $lessonIds) {
            if (count($lessonIds) > 1) {
                $candidates[] = [
                    'lesson_ids' => array_keys($lessonIds),
                    'type' => self::TYPE_GOROADIE_ID,
                    'evidence' => ["GoRoadie ID {$ref}"],
                ];
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, array{lesson_ids: array<int, int>, type: string, evidence: array<int, string>}>
     */
    protected function exactDuplicateCandidates(): array
    {
        return DB::table('lessons')
            ->join('orders', 'orders.id', '=', 'lessons.order_id')
            ->where('lessons.status', '!=', LessonStatus::CANCELLED->value)
            ->whereNotNull('lessons.instructor_id')
            ->whereNotNull('lessons.date')
            ->whereNotNull('lessons.start_time')
            ->groupBy('lessons.instructor_id', 'orders.student_id', 'lessons.date', 'lessons.start_time')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('GROUP_CONCAT(lessons.id) as lesson_ids')
            ->get()
            ->map(fn (object $row): array => [
                'lesson_ids' => array_map('intval', explode(',', (string) $row->lesson_ids)),
                'type' => self::TYPE_EXACT,
                'evidence' => [],
            ])
            ->all();
    }

    /**
     * @return array<int, array{lesson_ids: array<int, int>, type: string, evidence: array<int, string>}>
     */
    protected function movedInDrivePlusCandidates(): array
    {
        $candidates = [];

        ActivityLog::query()
            ->where('loggable_type', Student::class)
            ->whereNotNull('metadata->old_date')
            ->whereNotNull('metadata->old_start_time')
            ->lazyById(1000)
            ->each(function (ActivityLog $log) use (&$candidates): void {
                $movedLessonId = (int) ($log->metadata['lesson_id'] ?? $log->metadata['anchor_lesson_id'] ?? 0);
                $oldStartTime = self::normaliseTime((string) $log->metadata['old_start_time']);

                if ($movedLessonId === 0 || $oldStartTime === null) {
                    return;
                }

                $moved = DB::table('lessons')
                    ->join('orders', 'orders.id', '=', 'lessons.order_id')
                    ->where('lessons.id', $movedLessonId)
                    ->where('lessons.status', '!=', LessonStatus::CANCELLED->value)
                    ->where('orders.student_id', $log->loggable_id)
                    ->first(['lessons.id', 'lessons.instructor_id']);

                if ($moved === null) {
                    return;
                }

                $copies = DB::table('lessons')
                    ->join('orders', 'orders.id', '=', 'lessons.order_id')
                    ->where('orders.student_id', $log->loggable_id)
                    ->where('lessons.instructor_id', $moved->instructor_id)
                    ->where('lessons.id', '!=', $moved->id)
                    ->where('lessons.status', '!=', LessonStatus::CANCELLED->value)
                    ->whereDate('lessons.date', (string) $log->metadata['old_date'])
                    ->whereRaw('TIME(lessons.start_time) = TIME(?)', [$oldStartTime])
                    ->where(fn ($query) => $query
                        ->where('orders.payment_mode', PaymentMode::IMPORTED->value)
                        ->orWhereExists(fn ($exists) => $exists
                            ->from('import_mappings')
                            ->whereColumn('import_mappings.model_id', 'lessons.id')
                            ->where('import_mappings.entity', ImportMapping::ENTITY_LESSON)
                            ->where('import_mappings.action', ImportMapping::ACTION_CREATED)))
                    ->pluck('lessons.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($copies !== []) {
                    $candidates[] = [
                        'lesson_ids' => [(int) $moved->id, ...$copies],
                        'type' => self::TYPE_MOVED,
                        'evidence' => ["lesson #{$moved->id} moved from {$log->metadata['old_date']} {$oldStartTime} (activity log #{$log->id}, {$log->created_at?->toDateTimeString()})"],
                    ];
                }
            });

        return $candidates;
    }

    /**
     * Merge candidate groups that share a lesson (union-find).
     *
     * @param  array<int, array{lesson_ids: array<int, int>, type: string, evidence: array<int, string>}>  $candidates
     * @return array<int, array{lesson_ids: array<int, int>, types: array<int, string>, evidence: array<int, string>}>
     */
    protected function mergeCandidates(array $candidates): array
    {
        $parent = [];

        $find = function (int $id) use (&$parent, &$find): int {
            if (! isset($parent[$id])) {
                $parent[$id] = $id;
            }

            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };

        foreach ($candidates as $candidate) {
            $first = $find($candidate['lesson_ids'][0]);

            foreach ($candidate['lesson_ids'] as $lessonId) {
                $root = $find($lessonId);

                if ($root !== $first) {
                    $parent[$root] = $first;
                }
            }
        }

        $groups = [];

        foreach ($candidates as $candidate) {
            $root = $find($candidate['lesson_ids'][0]);
            $groups[$root] ??= ['lesson_ids' => [], 'types' => [], 'evidence' => []];
            $groups[$root]['lesson_ids'] = array_values(array_unique([...$groups[$root]['lesson_ids'], ...$candidate['lesson_ids']]));
            $groups[$root]['types'] = array_values(array_unique([...$groups[$root]['types'], $candidate['type']]));
            $groups[$root]['evidence'] = array_values(array_unique([...$groups[$root]['evidence'], ...$candidate['evidence']]));
        }

        return array_values($groups);
    }

    /**
     * Everything needed to judge a batch of lessons, loaded in a few queries.
     *
     * @param  array<int, int>  $lessonIds
     * @return array{lessons: Collection<int, Lesson>, payments: Collection<int, Collection<int, LessonPayment>>, finances: Collection<int, Collection<int, InstructorFinance>>, lesson_mappings: Collection<int, Collection<int, ImportMapping>>, diary_mappings: Collection<int, Collection<int, ImportMapping>>, move_logs: Collection<int, Collection<int, ActivityLog>>}
     */
    protected function loadContext(array $lessonIds): array
    {
        $lessons = Lesson::query()
            ->whereKey($lessonIds)
            ->where('status', '!=', LessonStatus::CANCELLED)
            ->with(['order.student', 'instructor.user', 'payout', 'reflectiveLog'])
            ->get()
            ->keyBy('id');

        $calendarItemIds = $lessons->pluck('calendar_item_id')->filter()->unique()->values()->all();
        $studentIds = $lessons->map(fn (Lesson $lesson) => $lesson->order?->student_id)->filter()->unique()->values()->all();

        $moveLogs = collect();

        ActivityLog::query()
            ->where('loggable_type', Student::class)
            ->whereIn('loggable_id', $studentIds)
            ->whereNotNull('metadata->old_date')
            ->get()
            ->each(function (ActivityLog $log) use ($moveLogs, $lessons): void {
                $metadata = $log->metadata ?? [];
                $ids = array_map('intval', array_filter([
                    $metadata['lesson_id'] ?? null,
                    $metadata['anchor_lesson_id'] ?? null,
                    ...($metadata['affected_lesson_ids'] ?? []),
                ]));

                foreach (array_unique($ids) as $id) {
                    if ($lessons->has($id)) {
                        $moveLogs->put($id, $moveLogs->get($id, collect())->push($log));
                    }
                }
            });

        return [
            'lessons' => $lessons,
            'payments' => LessonPayment::query()->whereIn('lesson_id', $lessonIds)->get()->groupBy('lesson_id'),
            'finances' => InstructorFinance::query()->whereIn('lesson_id', $lessonIds)->get(['id', 'lesson_id', 'amount_pence'])->groupBy('lesson_id'),
            'lesson_mappings' => ImportMapping::query()->where('entity', ImportMapping::ENTITY_LESSON)->whereIn('model_id', $lessonIds)->get()->groupBy('model_id'),
            'diary_mappings' => ImportMapping::query()->where('entity', ImportMapping::ENTITY_DIARY)->whereIn('model_id', $calendarItemIds)->get()->groupBy('model_id'),
            'move_logs' => $moveLogs,
        ];
    }

    /**
     * @param  array{lesson_ids: array<int, int>, types: array<int, string>, evidence: array<int, string>}  $group
     * @param  array<string, Collection>  $context
     * @return array{group_id: string, types: array<int, string>, keep_lesson_id: int, needs_review: bool, review_reasons: array<int, string>, rows: array<int, array<string, string|int|null>>}|null
     */
    protected function buildGroup(array $group, array $context): ?array
    {
        /** @var Collection<int, Lesson> $lessons */
        $lessons = collect($group['lesson_ids'])
            ->map(fn (int $id) => $context['lessons']->get($id))
            ->filter()
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();

        if ($lessons->count() < 2) {
            return null;
        }

        $facts = $lessons->mapWithKeys(fn (Lesson $lesson): array => [$lesson->id => $this->facts($lesson, $context)]);
        $reviewReasons = [];

        $edited = $lessons->filter(fn (Lesson $lesson): bool => $facts[$lesson->id]['drive_plus_changes'] !== []);
        $withPayments = fn (Collection $pool): Collection => $pool->filter(fn (Lesson $lesson): bool => $facts[$lesson->id]['has_stripe_money'] || $facts[$lesson->id]['finance_ids'] !== []);

        if ($edited->count() === 1) {
            $keep = $edited->first();
            $keepReason = 'Drive Plus version: '.implode('; ', $facts[$keep->id]['drive_plus_changes']);
        } else {
            $pool = $edited->isNotEmpty() ? $edited : $lessons;

            if ($edited->count() > 1) {
                $reviewReasons[] = 'more than one lesson has Drive Plus changes ('.$edited->pluck('id')->map(fn ($id) => "#{$id}")->implode(', ').') — can\'t tell which is the edited version';
            }

            $paid = $withPayments($pool);

            if ($paid->count() === 1) {
                $keep = $paid->first();
                $keepReason = 'has linked payments';
            } else {
                $keep = $pool->first();
                $keepReason = 'oldest lesson';
            }
        }

        $stripeLessons = $lessons->filter(fn (Lesson $lesson): bool => $facts[$lesson->id]['has_stripe_money']);

        if ($stripeLessons->count() > 1) {
            $reviewReasons[] = 'more than one lesson has a lesson payment or payout ('.$stripeLessons->pluck('id')->map(fn ($id) => "#{$id}")->implode(', ').')';
        }

        if ($lessons->pluck('instructor_id')->unique()->count() > 1) {
            $reviewReasons[] = 'lessons are with different instructors';
        }

        if ($lessons->map(fn (Lesson $lesson) => $lesson->order?->student_id)->unique()->count() > 1) {
            $reviewReasons[] = 'lessons are for different pupils';
        }

        if ($keep->status === LessonStatus::DRAFT) {
            $reviewReasons[] = "lesson #{$keep->id} to keep is a draft (unpaid hold)";
        }

        foreach ($lessons as $lesson) {
            if ($lesson->is($keep)) {
                continue;
            }

            $lessonFacts = $facts[$lesson->id];

            if ($lessonFacts['has_stripe_money']) {
                $reviewReasons[] = "lesson #{$lesson->id} to remove has a lesson payment or payout (Stripe) — never moved automatically";
            }

            if ($lesson->order?->payment_mode !== PaymentMode::IMPORTED) {
                $reviewReasons[] = "lesson #{$lesson->id} to remove is a Drive booking (payment mode ".($lesson->order?->payment_mode?->value ?? 'none').'), not an imported £0 lesson';
            }

            if ($lesson->reflectiveLog !== null && $keep->reflectiveLog !== null) {
                $reviewReasons[] = "lessons #{$lesson->id} and #{$keep->id} both have a reflective log";
            }

            if ($lessonFacts['drive_plus_changes'] !== []) {
                $reviewReasons[] = "lesson #{$lesson->id} to remove has Drive Plus changes";
            }
        }

        if (in_array(self::TYPE_MOVED, $group['types'], true)) {
            foreach ($lessons as $lesson) {
                if ($lesson->is($keep)) {
                    continue;
                }

                $movedBeforeImport = $context['move_logs']->get($keep->id, collect())
                    ->contains(fn (ActivityLog $log): bool => $log->created_at !== null && $lesson->created_at !== null && $log->created_at->lessThan($lesson->created_at));

                if (! $movedBeforeImport && ! in_array(self::TYPE_EXACT, $group['types'], true) && ! in_array(self::TYPE_GOROADIE_ID, $group['types'], true)) {
                    $reviewReasons[] = "lesson #{$lesson->id} already existed when #{$keep->id} was moved — may not be a re-imported copy";
                }
            }
        }

        $groupId = 'DUP-'.$lessons->min('id');
        $reviewReasons = array_values(array_unique($reviewReasons));
        $needsReview = $reviewReasons !== [];
        $types = array_values(array_intersect(self::types(), $group['types']));

        $rows = $lessons->map(function (Lesson $lesson) use ($keep, $keepReason, $facts, $group, $groupId, $types, $needsReview, $reviewReasons): array {
            $isKeep = $lesson->is($keep);
            $lessonFacts = $facts[$lesson->id];
            $student = $lesson->order?->student;
            $order = $lesson->order;

            return [
                'group_id' => $groupId,
                'group_types' => implode('|', $types),
                'instructor_id' => $lesson->instructor_id,
                'instructor_name' => $lesson->instructor?->user?->name,
                'pupil_id' => $order?->student_id,
                'pupil_name' => $student ? trim($student->first_name.' '.$student->surname) : null,
                'lesson_id' => $lesson->id,
                'order_id' => $lesson->order_id,
                'order_payment_mode' => $order?->payment_mode?->value,
                'date' => $lesson->date?->toDateString(),
                'start_time' => self::normaliseTime((string) $lesson->getRawOriginal('start_time')),
                'end_time' => self::normaliseTime((string) $lesson->getRawOriginal('end_time')),
                'status' => $lesson->status->value,
                'goroadie_ids' => implode('|', $lessonFacts['goroadie_ids']),
                'imported' => $lessonFacts['imported'] ? 'yes' : 'no',
                'import_run_ids' => implode('|', $lessonFacts['import_run_ids']),
                'created_at' => $lesson->created_at?->toDateTimeString(),
                'updated_at' => $lesson->updated_at?->toDateTimeString(),
                'drive_plus_changes' => implode('; ', $lessonFacts['drive_plus_changes']),
                'lesson_payment_ids' => implode('|', $lessonFacts['lesson_payment_ids']),
                'lesson_payment_statuses' => implode('|', $lessonFacts['lesson_payment_statuses']),
                'stripe_invoice_ids' => implode('|', $lessonFacts['stripe_invoice_ids']),
                'stripe_charge_ids' => implode('|', $lessonFacts['stripe_charge_ids']),
                'payout_id' => $lesson->payout?->id,
                'stripe_transfer_id' => $lesson->payout?->stripe_transfer_id,
                'order_stripe_ids' => implode('|', array_filter([
                    $order?->stripe_payment_intent_id,
                    $order?->stripe_charge_id,
                    $order?->stripe_subscription_id,
                    $order?->stripe_checkout_session_id,
                ])),
                'finance_ids' => implode('|', $lessonFacts['finance_ids']),
                'reflective_log_id' => $lesson->reflectiveLog?->id,
                'calendar_item_id' => $lesson->calendar_item_id,
                'decision' => $isKeep ? self::DECISION_KEEP : self::DECISION_REMOVE,
                'keep_lesson_id' => $keep->id,
                'reason' => $isKeep
                    ? 'KEEP — '.$keepReason
                    : 'REMOVE — duplicate of #'.$keep->id.' ('.implode('; ', [...$types, ...$group['evidence']]).')',
                'needs_manual_review' => $needsReview ? 'yes' : 'no',
                'review_reasons' => implode('; ', $reviewReasons),
            ];
        })->all();

        return [
            'group_id' => $groupId,
            'types' => $types,
            'keep_lesson_id' => $keep->id,
            'needs_review' => $needsReview,
            'review_reasons' => $reviewReasons,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, Collection>  $context
     * @return array{goroadie_ids: array<int, string>, imported: bool, import_run_ids: array<int, int>, drive_plus_changes: array<int, string>, lesson_payment_ids: array<int, int>, lesson_payment_statuses: array<int, string>, stripe_invoice_ids: array<int, string>, stripe_charge_ids: array<int, string>, finance_ids: array<int, int>, has_stripe_money: bool}
     */
    protected function facts(Lesson $lesson, array $context): array
    {
        $lessonMappings = $context['lesson_mappings']->get($lesson->id, collect());
        $diaryMappings = $lesson->calendar_item_id !== null ? $context['diary_mappings']->get($lesson->calendar_item_id, collect()) : collect();
        $payments = $context['payments']->get($lesson->id, collect());
        $imported = $lessonMappings->contains(fn (ImportMapping $mapping): bool => $mapping->action === ImportMapping::ACTION_CREATED)
            || $lesson->order?->payment_mode === PaymentMode::IMPORTED;

        $changes = $context['move_logs']->get($lesson->id, collect())
            ->map(fn (ActivityLog $log): string => 'moved from '.$log->metadata['old_date'].' '.self::normaliseTime((string) ($log->metadata['old_start_time'] ?? ''))." (activity log #{$log->id})")
            ->values()
            ->all();

        if ($lesson->summary !== null && ! $lesson->summary_from_import) {
            $changes[] = 'signed off in Drive Plus with an instructor summary';
        }

        if (! $imported) {
            $changes[] = 'booked in Drive Plus (not imported)';
        }

        return [
            'goroadie_ids' => $lessonMappings->concat($diaryMappings)->pluck('source_ref')->map(fn ($ref) => (string) $ref)->unique()->values()->all(),
            'imported' => $imported,
            'import_run_ids' => $lessonMappings->pluck('import_run_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->all(),
            'drive_plus_changes' => array_values(array_unique($changes)),
            'lesson_payment_ids' => $payments->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'lesson_payment_statuses' => $payments->map(fn (LessonPayment $payment): string => $payment->status->value.' '.$payment->amount_pence.'p')->all(),
            'stripe_invoice_ids' => $payments->pluck('stripe_invoice_id')->filter()->values()->all(),
            'stripe_charge_ids' => $payments->pluck('stripe_charge_id')->filter()->values()->all(),
            'finance_ids' => $context['finances']->get($lesson->id, collect())->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'has_stripe_money' => $payments->isNotEmpty() || $lesson->payout !== null,
        ];
    }

    public static function normaliseRef(string $ref): string
    {
        return mb_strtolower(trim($ref));
    }

    /**
     * "9:05", "09:05" or "09:05:00" → "09:05"; null when not a time.
     */
    public static function normaliseTime(string $time): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $matches)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }
}
