<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\User;
use App\Support\ImportCheckReport;
use App\Support\ImportValues;
use App\Support\PlaceholderEmails;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Check a staged import zip without writing anything: which rows are new,
 * which were imported before, which will be skipped and why, and anything
 * that blocks the import. Works through import_staged_rows file by file
 * (never the whole zip in memory). Follows the agreed import rules:
 * add-only, nothing overwritten, nothing deleted.
 *
 * Errors (block the import): a missing instructor/pupil ref, a duplicate ref,
 * a new instructor that can't be created. Everything else row-level is a
 * skip with a reason, or a note on how the row will be treated.
 */
class CheckStagedImportAction
{
    public const DIARY_ITEM_TYPES = ['lesson', 'mock_test', 'driving_test', 'blocked', 'holiday'];

    public const DIARY_STATUSES = ['completed', 'booked', 'cancelled'];

    public const STUDENT_STATUSES = ['active', 'inactive', 'on_hold', 'passed', 'failed', 'completed'];

    public const TEST_TYPES = ['theory', 'practical'];

    public const TEST_RESULTS = ['pass', 'fail', 'booked', 'cancelled'];

    public const PROGRESS_SCALES = [3, 5, 10];

    public const MILEAGE_TYPES = ['business', 'personal'];

    /** Full UK postcode, e.g. "LS14 6RX" (space optional). */
    public const FULL_POSTCODE = '/^[A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2}$/i';

    protected ImportCheckReport $report;

    protected ImportRun $run;

    protected string $today;

    /** @var array<string, string> instructor_ref => postcode, from instructors.csv */
    protected array $fileInstructors = [];

    /** @var array<string, true> */
    protected array $mappedInstructors = [];

    /** @var array<string, bool> DRIVE-<id> ref => exists */
    protected array $driveInstructors = [];

    /** @var array<string, string> student_ref => instructor_ref, from students.csv */
    protected array $fileStudents = [];

    /** @var array<string, true> */
    protected array $mappedStudents = [];

    /** @var array<string, true> student_refs the import will leave out */
    protected array $skippedStudents = [];

    /** @var array<string, true> diary refs in diary.csv that become lessons */
    protected array $lessonRefs = [];

    public function __construct(
        protected ParseReflectiveLogTextAction $parseReflectiveLogText,
    ) {}

    /**
     * @return array{files: array<string, array<string, mixed>>, errors: array<int, array{file: string, line: int, message: string}>, error_count: int, examples: array<string, mixed>}
     */
    public function __invoke(ImportRun $run, ?ImportCheckReport $report = null): array
    {
        $this->run = $run;
        $this->report = $report ?? new ImportCheckReport;
        $this->today = now()->toDateString();
        $this->mappedInstructors = $this->mappedRefs(ImportMapping::ENTITY_INSTRUCTOR);
        $this->mappedStudents = $this->mappedRefs(ImportMapping::ENTITY_STUDENT);

        $this->checkInstructors();
        $this->checkCoverage();
        $this->checkVehicles();
        $this->checkStudents();
        $this->checkPickupPoints();
        $this->checkDiary();
        $this->checkTests();
        $this->checkProgress();
        $this->checkReflectiveLogs();
        $this->checkFinances();
        $this->checkPaymentLessons();
        $this->checkMileage();

        return $this->report->toArray();
    }

    protected function checkInstructors(): void
    {
        $section = 'instructors';
        $rows = iterator_to_array($this->rows($section), false);
        $emails = array_map(fn (array $staged) => strtolower($staged['row']['email'] ?? ''), $rows);
        $accounts = $this->accountsByEmail($emails);
        $seenEmails = [];

        foreach ($rows as ['line' => $line, 'row' => $row]) {
            $ref = $row['instructor_ref'];
            $email = strtolower($row['email']);
            $this->report->row($section);

            if (ImportValues::driveInstructorId($ref) !== null) {
                $this->report->error('instructors.csv', $line, "'{$ref}' is an existing Drive+ instructor. Leave them out of instructors.csv and use the ref in the other files.");

                continue;
            }

            if (isset($this->fileInstructors[$ref])) {
                $this->report->error('instructors.csv', $line, "Duplicate instructor_ref '{$ref}'.");

                continue;
            }

            $this->fileInstructors[$ref] = strtoupper((string) ($row['postcode'] ?? ''));

            if (isset($seenEmails[$email])) {
                $this->report->error('instructors.csv', $line, "Email '{$email}' appears more than once.");
            }
            $seenEmails[$email] = true;

            if (isset($this->mappedInstructors[$ref])) {
                $this->report->alreadyImported($section);

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->report->error('instructors.csv', $line, "Invalid email '{$email}'.");

                continue;
            }

            $account = $accounts[$email] ?? null;

            if ($account === 'student' || $account === 'other') {
                $this->report->note($section, 'Email already on a non-instructor Drive account: placeholder email, excluded from emails (that account not changed)');
            }

            if ($account === 'instructor') {
                $this->report->note($section, 'Linked to an existing Drive instructor (account not changed)');

                continue;
            }

            if (($row['transmission_type'] ?? '') === '') {
                $this->report->error('instructors.csv', $line, 'transmission_type is required for a new instructor.');

                continue;
            }

            $this->report->new($section);
        }
    }

    protected function checkCoverage(): void
    {
        $section = 'coverage';
        $mapped = $this->mappedRefs(ImportMapping::ENTITY_LOCATION);

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row, 'instructor_ref' => $instructorRef]) {
            $this->report->row($section);

            if (! $this->knownInstructor('coverage.csv', $line, (string) $instructorRef)) {
                continue;
            }

            $sectors = ImportValues::postcodeSectors($row['postcode_sector']);

            if ($sectors === null) {
                $this->report->skip($section, 'Not a postcode sector', $line, $row['postcode_sector']);

                continue;
            }

            foreach ($sectors as $sector) {
                if ($sector === 'E14' && ! str_starts_with($this->fileInstructors[$instructorRef] ?? '', 'E14')) {
                    $this->report->skip($section, 'E14 dropped (instructor not based in E14)', $line, "{$instructorRef}:E14");

                    continue;
                }

                isset($mapped["{$instructorRef}:{$sector}"]) ? $this->report->alreadyImported($section) : $this->report->new($section);
            }
        }
    }

    protected function checkVehicles(): void
    {
        foreach ($this->rows('vehicles') as ['line' => $line, 'row' => $row]) {
            $this->report->row('vehicles');
            $this->report->skip('vehicles', 'Not imported (no vehicles are created)', $line, $row['registration']);

            if (! self::looksLikeRegistration($row['registration'])) {
                $this->report->note('vehicles', 'Registration looks wrong (ignored in finance/mileage notes)');
            }
        }
    }

    protected function checkStudents(): void
    {
        $section = 'students';
        $rows = iterator_to_array($this->rows($section), false);
        $accounts = $this->accountsByEmail(array_map(fn (array $staged) => strtolower($staged['row']['email'] ?? ''), $rows));
        $seenEmails = [];

        foreach ($rows as ['line' => $line, 'row' => $row, 'instructor_ref' => $instructorRef]) {
            $ref = $row['student_ref'];
            $email = strtolower($row['email']);
            $this->report->row($section);

            if (isset($this->fileStudents[$ref])) {
                $this->report->error('students.csv', $line, "Duplicate student_ref '{$ref}'.");

                continue;
            }

            $this->fileStudents[$ref] = (string) $instructorRef;

            if (! $this->knownInstructor('students.csv', $line, (string) $instructorRef)) {
                continue;
            }

            $status = $row['status'] ?? '';
            if ($status !== '' && ! in_array($status, self::STUDENT_STATUSES, true)) {
                $this->report->error('students.csv', $line, "Invalid status '{$status}'.");
            }

            if (isset($this->mappedStudents[$ref])) {
                $this->report->alreadyImported($section);

                continue;
            }

            if ($email === '' || PlaceholderEmails::isPlaceholder($email)) {
                $this->report->note($section, 'No real email: placeholder address, excluded from emails');
                $this->report->new($section);

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->skippedStudents[$ref] = true;
                $this->report->skip($section, 'Invalid email (pupil and their rows skipped)', $line, $ref);

                continue;
            }

            if (isset($seenEmails[$email])) {
                $this->report->error('students.csv', $line, "Email '{$email}' appears more than once.");

                continue;
            }
            $seenEmails[$email] = true;

            $account = $accounts[$email] ?? null;

            if ($account === 'student') {
                $this->report->note($section, 'Linked to an existing Drive pupil (account not changed)');

                continue;
            }

            if ($account !== null) {
                $this->skippedStudents[$ref] = true;
                $this->report->skip($section, 'Email belongs to a Drive account that isn\'t a pupil (pupil and their rows skipped)', $line, $ref);

                continue;
            }

            $this->report->new($section);
        }
    }

    protected function checkPickupPoints(): void
    {
        $section = 'pickup_points';

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row]) {
            $this->report->row($section);
            $studentRef = $row['student_ref'];

            if (! $this->usableStudent($section, 'pickup_points.csv', $line, $studentRef)) {
                continue;
            }

            if (! preg_match(self::FULL_POSTCODE, $row['postcode'])) {
                $this->report->skip($section, 'No full postcode', $line, $studentRef);

                continue;
            }

            $this->report->new($section);
        }

        $this->report->note($section, 'Added only for pupils with no pick-up point in Drive (checked at import)', 0);
    }

    protected function checkDiary(): void
    {
        $section = 'diary';
        $mappedDiary = $this->mappedRefs(ImportMapping::ENTITY_DIARY);
        $mappedLessons = $this->mappedRefs(ImportMapping::ENTITY_LESSON);
        $seen = [];

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row, 'instructor_ref' => $instructorRef]) {
            $ref = $row['diary_ref'];
            $studentRef = $row['student_ref'] ?? '';
            $this->report->row($section);

            if (isset($seen[$ref])) {
                $this->report->error('diary.csv', $line, "Duplicate diary_ref '{$ref}'.");

                continue;
            }
            $seen[$ref] = true;

            if (! $this->knownInstructor('diary.csv', $line, (string) $instructorRef)) {
                continue;
            }

            $type = ($row['item_type'] ?? '') !== '' ? $row['item_type'] : ($studentRef !== '' ? 'lesson' : 'blocked');

            if (! in_array($type, self::DIARY_ITEM_TYPES, true)) {
                $this->report->error('diary.csv', $line, "Invalid item_type '{$type}'.");

                continue;
            }

            $becomesLesson = in_array($type, ['lesson', 'mock_test'], true);

            if ($becomesLesson) {
                $this->lessonRefs[$ref] = true;
            }

            if ($studentRef !== '') {
                if (! $this->knownStudent('diary.csv', $line, $studentRef)) {
                    continue;
                }

                if (isset($this->skippedStudents[$studentRef])) {
                    $this->report->skip($section, 'Pupil not imported', $line, $ref);

                    continue;
                }

                if (isset($this->fileStudents[$studentRef]) && $this->fileStudents[$studentRef] !== $instructorRef) {
                    $this->report->skip($section, 'Pupil is now with another instructor', $line, $ref);

                    continue;
                }
            }

            if (($row['status'] ?? '') === 'cancelled') {
                $this->report->skip($section, 'Cancelled', $line, $ref);

                continue;
            }

            if (($row['status'] ?? '') !== '' && ! in_array($row['status'], self::DIARY_STATUSES, true)) {
                $this->report->error('diary.csv', $line, "Invalid status '{$row['status']}'.");

                continue;
            }

            if (! $this->validSlot($row['date'], $row['start_time'], $row['end_time'])) {
                $this->report->skip($section, 'Bad date or time (blank, before 2000, or end not after start)', $line, $ref);

                continue;
            }

            if (($row['price'] ?? '') !== '' && ImportValues::pence($row['price']) === null) {
                $this->report->note($section, 'Price unreadable (lesson imported at £0)');
            }

            if (isset($mappedDiary[$ref]) || isset($mappedLessons[$ref])) {
                $this->report->alreadyImported($section);

                continue;
            }

            $this->report->note($section, match (true) {
                $type === 'mock_test' => 'Mock test → lesson ("Mock test" summary, no charge)',
                $type === 'driving_test' => 'Driving test → practical test slot',
                $type === 'lesson' && $studentRef === '' => 'Lesson with no pupil → diary block',
                $becomesLesson => ImportValues::date($row['date'])->toDateString() < $this->today ? 'Lesson (completed)' : 'Lesson (booked)',
                default => 'Diary block ('.$type.')',
            });
            $this->report->new($section);
        }
    }

    protected function checkTests(): void
    {
        $section = 'tests';
        $mapped = $this->mappedRefs(ImportMapping::ENTITY_TEST);
        $seen = [];

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row]) {
            $ref = $row['test_ref'];
            $this->report->row($section);

            if (isset($seen[$ref])) {
                $this->report->error('tests.csv', $line, "Duplicate test_ref '{$ref}'.");

                continue;
            }
            $seen[$ref] = true;

            if (! $this->usableStudent($section, 'tests.csv', $line, $row['student_ref'])) {
                continue;
            }

            $type = strtolower($row['test_type']);
            $result = strtolower($row['result']);
            $date = ImportValues::date($row['date']);

            if (! in_array($type, self::TEST_TYPES, true) || ! in_array($result, self::TEST_RESULTS, true)) {
                $this->report->skip($section, 'Unknown test type or result', $line, $ref);

                continue;
            }

            if ($date === null) {
                $this->report->skip($section, 'Bad date', $line, $ref);

                continue;
            }

            if (isset($mapped[$ref])) {
                $this->report->alreadyImported($section);

                continue;
            }

            $isFuture = $date->toDateString() > $this->today;

            if ($result === 'fail' && $isFuture) {
                $this->report->note($section, 'Future test marked fail → imported as booked');
            } elseif ($result === 'booked' && ! $isFuture && $type === 'theory') {
                $this->report->note($section, 'Past theory test still booked (imported as booked, checklist not ticked)');
            } elseif ($result === 'pass') {
                $this->report->note($section, 'Pass → ticks "Sit '.$type.' test" with the date (if not already ticked)');
            }

            $this->report->new($section);
        }
    }

    protected function checkProgress(): void
    {
        $section = 'progress';
        $mapped = $this->mappedRefs(ImportMapping::ENTITY_PROGRESS);
        $latest = [];

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row]) {
            $this->report->row($section);
            $studentRef = $row['student_ref'];

            if (! $this->usableStudent($section, 'progress.csv', $line, $studentRef)) {
                continue;
            }

            $scale = (int) $row['scale_max'];
            $score = $row['score'];

            if (! in_array($scale, self::PROGRESS_SCALES, true)) {
                $this->report->skip($section, 'Unknown scale (not 3, 5 or 10)', $line, $studentRef);

                continue;
            }

            if (! ctype_digit($score) || (int) $score < 1 || (int) $score > $scale) {
                $this->report->skip($section, 'Score missing, 0 or out of range', $line, $studentRef);

                continue;
            }

            $latest[self::progressRef($studentRef, $row['category'], $row['skill'])] = true;
        }

        $alreadyImported = count(array_intersect_key($latest, $mapped));
        $this->report->note($section, 'Skills to score (latest score per pupil per skill)', count($latest) - $alreadyImported);
        $this->report->note($section, 'Skills already imported', $alreadyImported);
        $this->report->note($section, 'Added only where the pupil has no score for that skill (checked at import)', 0);
    }

    protected function checkReflectiveLogs(): void
    {
        $section = 'reflective_logs';
        $mapped = $this->mappedRefs(ImportMapping::ENTITY_REFLECTIVE_LOG);
        $mappedLessons = $this->mappedRefs(ImportMapping::ENTITY_LESSON);
        $seen = [];

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row]) {
            $ref = $row['log_ref'];
            $this->report->row($section);

            if (isset($seen[$ref])) {
                $this->report->error('reflective_logs.csv', $line, "Duplicate log_ref '{$ref}'.");

                continue;
            }
            $seen[$ref] = true;

            if (! $this->usableStudent($section, 'reflective_logs.csv', $line, $row['student_ref'])) {
                continue;
            }

            $diaryRef = $row['diary_ref'] ?? '';

            if ($diaryRef === '' || (! isset($this->lessonRefs[$diaryRef]) && ! isset($mappedLessons[$diaryRef]))) {
                $this->report->skip($section, 'No lesson to attach it to', $line, $ref);

                continue;
            }

            if (ParseReflectiveLogTextAction::isEmpty(($this->parseReflectiveLogText)($row['text']))) {
                $this->report->skip($section, 'Empty (every section blank)', $line, $ref);

                continue;
            }

            isset($mapped[$ref]) ? $this->report->alreadyImported($section) : $this->report->new($section);
        }
    }

    protected function checkFinances(): void
    {
        $section = 'finances';
        $mapped = $this->mappedRefs(ImportMapping::ENTITY_FINANCE);
        $paymentMethods = array_keys((array) config('finances.payment_methods'));
        $seen = [];

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row, 'instructor_ref' => $instructorRef]) {
            $ref = $row['finance_ref'];
            $this->report->row($section);

            if (isset($seen[$ref])) {
                $this->report->error('finances.csv', $line, "Duplicate finance_ref '{$ref}'.");

                continue;
            }
            $seen[$ref] = true;

            if (! $this->knownInstructor('finances.csv', $line, (string) $instructorRef)) {
                continue;
            }

            $type = $row['type'];

            if (! in_array($type, ['payment', 'expense'], true) || ImportValues::date($row['date']) === null || ImportValues::pence($row['amount']) === null) {
                $this->report->skip($section, 'Bad type, date or amount', $line, $ref);

                continue;
            }

            if (isset($mapped[$ref])) {
                $this->report->alreadyImported($section);

                if (($row['student_ref'] ?? '') !== '' || ($row['diary_ref'] ?? '') !== '') {
                    $this->report->note($section, 'Existing row: pupil/lesson link filled (new columns)');
                }

                continue;
            }

            $category = $row['category'] ?? '';
            if ($category === '' || ! array_key_exists($category, (array) config("finances.{$type}_categories"))) {
                $this->report->note($section, 'No recognised category → "imported"');
            }

            if (($row['payment_method'] ?? '') !== '' && ! in_array($row['payment_method'], $paymentMethods, true)) {
                $this->report->note($section, 'Unknown payment method (left blank)');
            }

            if (in_array($row['payment_status'] ?? '', ['refunded', 'outstanding'], true)) {
                $this->report->note($section, 'Refunded/outstanding (noted on the row)');
            }

            if (($row['receipt_file'] ?? '') !== '') {
                $this->report->note($section, 'Names a receipt file (attach later with import:receipts)');
            }

            if (mb_strlen($row['description']) > 255) {
                $this->report->note($section, 'Description over 255 characters (shortened)');
            }

            $this->report->new($section);
        }
    }

    protected function checkPaymentLessons(): void
    {
        foreach ($this->rows('payment_lessons') as ['line' => $line, 'row' => $row]) {
            $this->report->row('payment_lessons');
            $this->report->skip('payment_lessons', 'Not imported (each payment is split across a duplicated lesson)', $line, $row['finance_ref']);
        }
    }

    protected function checkMileage(): void
    {
        $section = 'mileage';
        $mapped = $this->mappedRefs(ImportMapping::ENTITY_MILEAGE);
        $seen = [];

        foreach ($this->rows($section) as ['line' => $line, 'row' => $row, 'instructor_ref' => $instructorRef]) {
            $ref = $row['mileage_ref'];
            $this->report->row($section);

            if (isset($seen[$ref])) {
                $this->report->error('mileage.csv', $line, "Duplicate mileage_ref '{$ref}'.");

                continue;
            }
            $seen[$ref] = true;

            if (! $this->knownInstructor('mileage.csv', $line, (string) $instructorRef)) {
                continue;
            }

            $start = $row['start_odometer'];
            $end = $row['end_odometer'];
            $clean = ImportValues::date($row['date']) !== null
                && ctype_digit($start) && ctype_digit($end) && (int) $end >= (int) $start
                && in_array(strtolower($row['type'] ?? ''), self::MILEAGE_TYPES, true);

            if (! $clean) {
                $this->report->skip($section, 'Not clean (date, odometers or type)', $line, $ref);

                continue;
            }

            isset($mapped[$ref]) ? $this->report->alreadyImported($section) : $this->report->new($section);
        }
    }

    /**
     * A pupil ref that other files may use: known, and not left out of the import.
     */
    protected function usableStudent(string $section, string $fileName, int $line, string $studentRef): bool
    {
        if (! $this->knownStudent($fileName, $line, $studentRef)) {
            return false;
        }

        if (isset($this->skippedStudents[$studentRef])) {
            $this->report->skip($section, 'Pupil not imported', $line, $studentRef);

            return false;
        }

        return true;
    }

    protected function knownStudent(string $fileName, int $line, string $studentRef): bool
    {
        if (isset($this->fileStudents[$studentRef]) || isset($this->mappedStudents[$studentRef])) {
            return true;
        }

        $this->report->error($fileName, $line, "Unknown student_ref '{$studentRef}'.");

        return false;
    }

    protected function knownInstructor(string $fileName, int $line, string $instructorRef): bool
    {
        if (isset($this->fileInstructors[$instructorRef]) || isset($this->mappedInstructors[$instructorRef])) {
            return true;
        }

        $driveId = ImportValues::driveInstructorId($instructorRef);

        if ($driveId !== null) {
            $this->driveInstructors[$instructorRef] ??= Instructor::query()->whereKey($driveId)->exists();

            if ($this->driveInstructors[$instructorRef]) {
                return true;
            }
        }

        $this->report->error($fileName, $line, "Unknown instructor_ref '{$instructorRef}'.");

        return false;
    }

    protected function validSlot(string $date, string $start, string $end): bool
    {
        $parsedDate = ImportValues::date($date);
        $startTime = ImportValues::time($start);
        $endTime = ImportValues::time($end);

        return $parsedDate !== null
            && $parsedDate->year >= 2000
            && $startTime !== null
            && $endTime !== null
            && $endTime > $startTime;
    }

    /**
     * The ref a pupil's imported score for one skill is mapped under.
     */
    public static function progressRef(string $studentRef, string $category, string $skill): string
    {
        return mb_substr($studentRef.':'.mb_strtolower(trim($category)).':'.mb_strtolower(trim($skill)), 0, 191);
    }

    /**
     * Whether a value looks like a UK registration rather than free text
     * (e.g. "AUTOMATIC", "OWNCAR", "DL23FLAVAUXHALLCORSA").
     */
    public static function looksLikeRegistration(string $value): bool
    {
        $plate = strtoupper(str_replace(' ', '', $value));

        return strlen($plate) >= 2 && strlen($plate) <= 8 && preg_match('/[0-9]/', $plate) === 1;
    }

    /**
     * Staged rows of one file, oldest first, read in chunks.
     *
     * @return Generator<int, array{line: int, row: array<string, string>, instructor_ref: string|null, source_ref: string|null}>
     */
    protected function rows(string $section): Generator
    {
        $query = DB::table('import_staged_rows')
            ->where('import_run_id', $this->run->id)
            ->where('file', $section)
            ->select(['id', 'line', 'row', 'instructor_ref', 'source_ref']);

        foreach ($query->lazyById(2000) as $staged) {
            yield [
                'line' => (int) $staged->line,
                'row' => (array) json_decode((string) $staged->row, true),
                'instructor_ref' => $staged->instructor_ref,
                'source_ref' => $staged->source_ref,
            ];
        }
    }

    /**
     * @return array<string, true>
     */
    protected function mappedRefs(string $entity): array
    {
        return array_fill_keys(
            ImportMapping::query()->where('entity', $entity)->toBase()->pluck('source_ref')->all(),
            true,
        );
    }

    /**
     * What kind of Drive account each email already belongs to.
     *
     * @param  array<int, string>  $emails
     * @return array<string, string> email => `instructor`, `student` or `other`
     */
    protected function accountsByEmail(array $emails): array
    {
        $accounts = [];

        foreach (array_chunk(array_values(array_unique(array_filter($emails))), 1000) as $chunk) {
            User::query()
                ->whereIn('email', $chunk)
                ->with(['instructor:id,user_id', 'student:id,user_id'])
                ->get(['id', 'email'])
                ->each(function (User $user) use (&$accounts): void {
                    $accounts[strtolower($user->email)] = match (true) {
                        $user->instructor !== null => 'instructor',
                        $user->student !== null => 'student',
                        default => 'other',
                    };
                });
        }

        return $accounts;
    }
}
