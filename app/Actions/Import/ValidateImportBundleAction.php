<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\InstructorStatus;
use App\Enums\PdiStatus;
use App\Enums\TransmissionType;
use App\Models\ImportMapping;
use App\Models\Instructor;
use App\Models\User;
use App\Support\ImportValues;

class ValidateImportBundleAction
{
    public const DIARY_STATUSES = ['completed', 'booked', 'cancelled'];

    public const STUDENT_STATUSES = ['active', 'inactive', 'on_hold', 'passed', 'failed', 'completed'];

    /** @var array<int, array{file: string, line: int, message: string}> */
    protected array $errors = [];

    /** @var array<int, array{file: string, line: int, message: string}> */
    protected array $skipped = [];

    /**
     * Check every row of a bundle before anything is written.
     *
     * Refs must resolve within the bundle or against an earlier import run
     * (import_mappings). Rows whose ref was already imported are not
     * re-checked against the database — the importer skips them. Some rows
     * are not errors but are left out of the import and listed in skipped():
     * students with an invalid email or one already on Drive+ (plus their
     * diary rows), and coverage rows with an invalid postcode sector.
     *
     * @param  array<string, array<int|string, mixed>>  $bundle  From ReadImportBundleAction
     * @return array<int, array{file: string, line: int, message: string}>
     */
    public function __invoke(array $bundle): array
    {
        $this->errors = [];
        $this->skipped = [];

        $instructorRefs = $this->validateInstructors($bundle['instructors']) + $this->driveInstructorRefs($bundle);
        $this->validateCoverage($bundle['coverage'], $instructorRefs);
        $studentInstructors = $this->validateStudents($bundle['students'], $instructorRefs);
        $this->validateDiary($bundle['diary'], $instructorRefs, $studentInstructors);
        $this->validateFinances($bundle['finances'], $instructorRefs, $bundle['attachments'] ?? []);

        return $this->errors;
    }

    /**
     * @param  array<int, array<string, string|int>>  $rows
     * @return array<string, true> Every instructor ref a later file may use
     */
    protected function validateInstructors(array $rows): array
    {
        $refs = [];
        $emails = [];
        $mappedRefs = $this->mappedRefs(ImportMapping::ENTITY_INSTRUCTOR);
        $existingUsers = $this->existingUsersByEmail($rows);

        foreach ($rows as $row) {
            $ref = $this->value($row, 'instructor_ref');
            $email = strtolower($this->value($row, 'email'));

            if (! $this->requireFields('instructors.csv', $row, ['instructor_ref', 'name', 'email'])) {
                continue;
            }

            if (ImportValues::driveInstructorId($ref) !== null) {
                $this->fail('instructors.csv', $row, "'{$ref}' is an existing Drive+ instructor. Leave them out of instructors.csv and use the ref in the other files.");

                continue;
            }

            if (isset($refs[$ref])) {
                $this->fail('instructors.csv', $row, "Duplicate instructor_ref '{$ref}'.");

                continue;
            }
            $refs[$ref] = true;

            if (isset($emails[$email])) {
                $this->fail('instructors.csv', $row, "Email '{$email}' appears more than once.");
            }
            $emails[$email] = true;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('instructors.csv', $row, "Invalid email '{$email}'.");
            }

            $this->checkEnum('instructors.csv', $row, 'status', InstructorStatus::values());
            $this->checkEnum('instructors.csv', $row, 'pdi_status', PdiStatus::values());

            if (isset($mappedRefs[$ref])) {
                continue;
            }

            // Any account with an instructor profile (including an owner who also
            // teaches) is linked as-is. An account without one can't be reused,
            // and a second user can't be created on the same email.
            $hasInstructorProfile = $existingUsers[$email] ?? null;

            if ($hasInstructorProfile === true) {
                continue;
            }

            if ($hasInstructorProfile === false) {
                $this->fail('instructors.csv', $row, "Email '{$email}' belongs to an existing account that has no instructor profile.");

                continue;
            }

            // A new instructor needs a transmission type; an existing one is linked as-is.
            if ($this->value($row, 'transmission_type') === '') {
                $this->fail('instructors.csv', $row, 'transmission_type is required for a new instructor.');
            } else {
                $this->checkEnum('instructors.csv', $row, 'transmission_type', TransmissionType::values());
            }
        }

        return $refs + $mappedRefs;
    }

    /**
     * @param  array<int, array<string, string|int>>  $rows
     * @param  array<string, true>  $instructorRefs
     */
    protected function validateCoverage(array $rows, array $instructorRefs): void
    {
        foreach ($rows as $row) {
            if (! $this->requireFields('coverage.csv', $row, ['instructor_ref', 'postcode_sector'])) {
                continue;
            }

            $this->checkRef('coverage.csv', $row, 'instructor_ref', $instructorRefs);

            if (ImportValues::postcodeSectors($this->value($row, 'postcode_sector')) === null) {
                $this->skip('coverage.csv', $row, "Invalid postcode sector '{$this->value($row, 'postcode_sector')}' (expected e.g. EH12). Row will be skipped.");
            }
        }
    }

    /**
     * @param  array<int, array<string, string|int>>  $rows
     * @param  array<string, true>  $instructorRefs
     * @return array<string, string|null> student_ref => instructor_ref (null when mapped from an earlier run)
     */
    protected function validateStudents(array $rows, array $instructorRefs): array
    {
        $students = [];
        $emails = [];
        $mappedRefs = $this->mappedRefs(ImportMapping::ENTITY_STUDENT);
        $existingUsers = $this->existingUsersByEmail($rows);

        foreach ($rows as $row) {
            if (! $this->requireFields('students.csv', $row, ['student_ref', 'instructor_ref', 'first_name', 'surname', 'email'])) {
                continue;
            }

            $ref = $this->value($row, 'student_ref');
            $email = strtolower($this->value($row, 'email'));

            if (array_key_exists($ref, $students)) {
                $this->fail('students.csv', $row, "Duplicate student_ref '{$ref}'.");

                continue;
            }
            $students[$ref] = $this->value($row, 'instructor_ref');

            $this->checkRef('students.csv', $row, 'instructor_ref', $instructorRefs);

            if (isset($emails[$email])) {
                $this->fail('students.csv', $row, "Email '{$email}' appears more than once.");
            }
            $emails[$email] = true;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->skip('students.csv', $row, "Invalid email '{$email}'. Student and their diary lessons will be skipped.");

                continue;
            }

            $contactEmail = $this->value($row, 'contact_email');
            if ($contactEmail !== '' && ! filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                $this->fail('students.csv', $row, "Invalid contact_email '{$contactEmail}'.");
            }

            $this->checkEnum('students.csv', $row, 'status', self::STUDENT_STATUSES);

            if (! isset($mappedRefs[$ref]) && isset($existingUsers[$email])) {
                $this->skip('students.csv', $row, "Email '{$email}' is already on Drive+. Student and their diary lessons will be skipped.");
            }
        }

        foreach (array_keys($mappedRefs) as $ref) {
            $students[$ref] ??= null;
        }

        return $students;
    }

    /**
     * @param  array<int, array<string, string|int>>  $rows
     * @param  array<string, true>  $instructorRefs
     * @param  array<string, string|null>  $studentInstructors
     */
    protected function validateDiary(array $rows, array $instructorRefs, array $studentInstructors): void
    {
        $refs = [];
        $today = now()->startOfDay();

        foreach ($rows as $row) {
            if (! $this->requireFields('diary.csv', $row, ['diary_ref', 'instructor_ref', 'date', 'start_time', 'end_time'])) {
                continue;
            }

            $ref = $this->value($row, 'diary_ref');

            if (isset($refs[$ref])) {
                $this->fail('diary.csv', $row, "Duplicate diary_ref '{$ref}'.");

                continue;
            }
            $refs[$ref] = true;

            $instructorRef = $this->value($row, 'instructor_ref');
            $this->checkRef('diary.csv', $row, 'instructor_ref', $instructorRefs);

            $studentRef = $this->value($row, 'student_ref');
            if ($studentRef !== '') {
                if (! array_key_exists($studentRef, $studentInstructors)) {
                    $this->fail('diary.csv', $row, "Unknown student_ref '{$studentRef}'.");
                } elseif ($studentInstructors[$studentRef] !== null && $studentInstructors[$studentRef] !== $instructorRef) {
                    $this->fail('diary.csv', $row, "Student '{$studentRef}' belongs to instructor '{$studentInstructors[$studentRef]}', not '{$instructorRef}'.");
                }
            }

            $date = ImportValues::date($this->value($row, 'date'));
            $start = ImportValues::time($this->value($row, 'start_time'));
            $end = ImportValues::time($this->value($row, 'end_time'));

            if (! $date) {
                $this->fail('diary.csv', $row, "Invalid date '{$this->value($row, 'date')}' (use YYYY-MM-DD or DD/MM/YYYY).");
            }
            if (! $start || ! $end) {
                $this->fail('diary.csv', $row, 'Invalid start_time/end_time (use HH:MM).');
            } elseif ($end <= $start) {
                $this->fail('diary.csv', $row, 'end_time must be after start_time.');
            }

            $this->checkEnum('diary.csv', $row, 'status', self::DIARY_STATUSES);

            if ($this->value($row, 'status') === 'completed' && $date && $date->greaterThan($today)) {
                $this->fail('diary.csv', $row, 'A future diary item cannot be completed.');
            }

            $price = $this->value($row, 'price');
            if ($price !== '' && ImportValues::pence($price) === null) {
                $this->fail('diary.csv', $row, "Invalid price '{$price}' (use pounds, e.g. 35.00).");
            }

            $mileage = $this->value($row, 'mileage');
            if ($mileage !== '' && ! ctype_digit($mileage)) {
                $this->fail('diary.csv', $row, "Invalid mileage '{$mileage}' (whole miles).");
            }
        }
    }

    /**
     * @param  array<int, array<string, string|int>>  $rows
     * @param  array<string, true>  $instructorRefs
     * @param  array<string, array{name: string, size: int}|null>  $attachments
     */
    protected function validateFinances(array $rows, array $instructorRefs, array $attachments): void
    {
        $refs = [];

        foreach ($rows as $row) {
            if (! $this->requireFields('finances.csv', $row, ['finance_ref', 'instructor_ref', 'type', 'date', 'amount', 'description'])) {
                continue;
            }

            $ref = $this->value($row, 'finance_ref');

            if (isset($refs[$ref])) {
                $this->fail('finances.csv', $row, "Duplicate finance_ref '{$ref}'.");

                continue;
            }
            $refs[$ref] = true;

            $this->checkRef('finances.csv', $row, 'instructor_ref', $instructorRefs);

            $type = $this->value($row, 'type');
            if (! in_array($type, ['payment', 'expense'], true)) {
                $this->fail('finances.csv', $row, "Invalid type '{$type}' (payment or expense).");
            } else {
                $this->checkEnum('finances.csv', $row, 'category', array_keys((array) config("finances.{$type}_categories")));
            }

            $this->checkEnum('finances.csv', $row, 'payment_method', array_keys((array) config('finances.payment_methods')));

            if (! ImportValues::date($this->value($row, 'date'))) {
                $this->fail('finances.csv', $row, "Invalid date '{$this->value($row, 'date')}' (use YYYY-MM-DD or DD/MM/YYYY).");
            }

            $amount = ImportValues::pence($this->value($row, 'amount'));
            if ($amount === null) {
                $this->fail('finances.csv', $row, "Invalid amount '{$this->value($row, 'amount')}' (pounds, 0 or more, e.g. 35.00).");
            }

            if (mb_strlen($this->value($row, 'description')) > 255) {
                $this->fail('finances.csv', $row, 'description must be 255 characters or fewer.');
            }

            $this->validateReceipt($row, $attachments);
        }
    }

    /**
     * A receipt_file must name a PDF/JPG/PNG inside the zip, within the normal
     * receipt size limit. Matched by file name, so any folder in the zip works.
     *
     * @param  array<string, string|int>  $row
     * @param  array<string, array{name: string, size: int}|null>  $attachments
     */
    protected function validateReceipt(array $row, array $attachments): void
    {
        $receiptFile = $this->value($row, 'receipt_file');

        if ($receiptFile === '') {
            return;
        }

        $basename = strtolower(basename(str_replace('\\', '/', $receiptFile)));
        $allowed = (array) config('finances.receipt.allowed_mimes');
        $maxBytes = (int) config('finances.receipt.max_size_kb') * 1024;

        if (! in_array(pathinfo($basename, PATHINFO_EXTENSION), $allowed, true)) {
            $this->fail('finances.csv', $row, "Receipt '{$receiptFile}' must be one of: ".implode(', ', $allowed).'.');
        } elseif (! array_key_exists($basename, $attachments)) {
            $this->fail('finances.csv', $row, "Receipt '{$receiptFile}' is not in the zip.");
        } elseif ($attachments[$basename] === null) {
            $this->fail('finances.csv', $row, "More than one file in the zip is called '{$basename}' — receipt file names must be unique.");
        } elseif ($attachments[$basename]['size'] > $maxBytes) {
            $this->fail('finances.csv', $row, "Receipt '{$receiptFile}' is larger than ".($maxBytes / 1024 / 1024).'MB.');
        }
    }

    /**
     * @param  array<string, string|int>  $row
     * @param  array<int, string>  $fields
     */
    protected function requireFields(string $file, array $row, array $fields): bool
    {
        $missing = array_filter($fields, fn (string $field) => $this->value($row, $field) === '');

        if ($missing !== []) {
            $this->fail($file, $row, 'Missing required: '.implode(', ', $missing).'.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, string|int>  $row
     * @param  array<int, string>  $allowed
     */
    protected function checkEnum(string $file, array $row, string $field, array $allowed): void
    {
        $value = $this->value($row, $field);

        if ($value !== '' && ! in_array($value, $allowed, true)) {
            $this->fail($file, $row, "Invalid {$field} '{$value}' (allowed: ".implode(', ', $allowed).').');
        }
    }

    /**
     * @param  array<string, string|int>  $row
     * @param  array<string, true>  $known
     */
    protected function checkRef(string $file, array $row, string $field, array $known): void
    {
        $ref = $this->value($row, $field);

        if (! isset($known[$ref])) {
            $this->fail($file, $row, "Unknown {$field} '{$ref}'.");
        }
    }

    /**
     * DRIVE-<id> refs used anywhere in the bundle that point at an existing
     * instructor. A ref with no matching instructor is left out, so the row
     * using it is reported as unknown.
     *
     * @param  array<string, array<int|string, mixed>>  $bundle
     * @return array<string, true>
     */
    protected function driveInstructorRefs(array $bundle): array
    {
        $ids = [];

        foreach (array_keys(ReadImportBundleAction::FILES) as $section) {
            foreach ($bundle[$section] as $row) {
                $ref = $this->value($row, 'instructor_ref');
                $id = ImportValues::driveInstructorId($ref);

                if ($id !== null) {
                    $ids[$ref] = $id;
                }
            }
        }

        $existing = Instructor::query()->whereKey(array_unique($ids))->pluck('id')->flip();

        return collect($ids)
            ->filter(fn (int $id) => $existing->has($id))
            ->map(fn () => true)
            ->all();
    }

    /**
     * Rows from the last check that will be left out of the import.
     *
     * @return array<int, array{file: string, line: int, message: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * Existing accounts for the rows' emails, loaded in chunks so a large
     * bundle is checked in a handful of queries rather than one per row.
     *
     * @param  array<int, array<string, string|int>>  $rows
     * @return array<string, bool> lowercased email => whether the account has an instructor profile
     */
    protected function existingUsersByEmail(array $rows): array
    {
        $emails = collect($rows)
            ->map(fn (array $row) => strtolower($this->value($row, 'email')))
            ->filter()
            ->unique()
            ->values();

        $existing = [];

        foreach ($emails->chunk(1000) as $chunk) {
            User::query()
                ->whereIn('email', $chunk->all())
                ->withExists('instructor')
                ->get(['id', 'email'])
                ->each(function (User $user) use (&$existing): void {
                    $existing[strtolower($user->email)] = (bool) $user->instructor_exists;
                });
        }

        return $existing;
    }

    /**
     * @return array<string, true>
     */
    protected function mappedRefs(string $entity): array
    {
        return ImportMapping::query()
            ->where('entity', $entity)
            ->pluck('source_ref')
            ->mapWithKeys(fn (string $ref) => [$ref => true])
            ->all();
    }

    /**
     * @param  array<string, string|int>  $row
     */
    protected function value(array $row, string $field): string
    {
        return trim((string) ($row[$field] ?? ''));
    }

    /**
     * @param  array<string, string|int>  $row
     */
    protected function fail(string $file, array $row, string $message): void
    {
        $this->errors[] = [
            'file' => $file,
            'line' => (int) ($row['_line'] ?? 0),
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, string|int>  $row
     */
    protected function skip(string $file, array $row, string $message): void
    {
        $this->skipped[] = [
            'file' => $file,
            'line' => (int) ($row['_line'] ?? 0),
            'message' => $message,
        ];
    }
}
