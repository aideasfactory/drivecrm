<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The files an import zip may hold (the Go Roadie export structure, which is a
 * superset of the original 5-file bundle), the columns each one must have, and
 * which column identifies each row. Every file is optional; a zip needs at
 * least one. Files are matched by name anywhere in the zip.
 */
class ImportBundleLayout
{
    public const MANIFEST = 'manifest.csv';

    /**
     * Section => file name, in the order rows are staged and checked
     * (students before the files that only carry a student_ref).
     *
     * @var array<string, string>
     */
    public const FILES = [
        'instructors' => 'instructors.csv',
        'coverage' => 'coverage.csv',
        'vehicles' => 'vehicles.csv',
        'students' => 'students.csv',
        'pickup_points' => 'pickup_points.csv',
        'diary' => 'diary.csv',
        'tests' => 'tests.csv',
        'progress' => 'progress.csv',
        'reflective_logs' => 'reflective_logs.csv',
        'finances' => 'finances.csv',
        'payment_lessons' => 'payment_lessons.csv',
        'mileage' => 'mileage.csv',
    ];

    /**
     * Columns a file must have for its rows to be read at all.
     *
     * @var array<string, array<int, string>>
     */
    public const REQUIRED_COLUMNS = [
        'instructors' => ['instructor_ref', 'name', 'email'],
        'coverage' => ['instructor_ref', 'postcode_sector'],
        'vehicles' => ['instructor_ref', 'registration'],
        'students' => ['student_ref', 'instructor_ref', 'first_name', 'surname', 'email'],
        'pickup_points' => ['student_ref', 'address', 'postcode'],
        'diary' => ['diary_ref', 'instructor_ref', 'date', 'start_time', 'end_time'],
        'tests' => ['test_ref', 'student_ref', 'test_type', 'date', 'result'],
        'progress' => ['student_ref', 'category', 'skill', 'score', 'scale_max'],
        'reflective_logs' => ['log_ref', 'student_ref', 'text'],
        'finances' => ['finance_ref', 'instructor_ref', 'type', 'date', 'amount', 'description'],
        'payment_lessons' => ['finance_ref', 'diary_ref'],
        'mileage' => ['mileage_ref', 'instructor_ref', 'date', 'start_odometer', 'end_odometer'],
    ];

    /**
     * The column holding each row's own ref (null: the file has none).
     *
     * @var array<string, string|null>
     */
    public const REF_COLUMNS = [
        'instructors' => 'instructor_ref',
        'coverage' => null,
        'vehicles' => 'registration',
        'students' => 'student_ref',
        'pickup_points' => null,
        'diary' => 'diary_ref',
        'tests' => 'test_ref',
        'progress' => null,
        'reflective_logs' => 'log_ref',
        'finances' => 'finance_ref',
        'payment_lessons' => 'finance_ref',
        'mileage' => 'mileage_ref',
    ];

    /**
     * Sections whose rows are grouped under the instructor of the pupil they
     * belong to (from students.csv): files without an instructor_ref, and
     * progress, which is scored against the pupil's current instructor's
     * framework.
     *
     * @var array<int, string>
     */
    public const INSTRUCTOR_FROM_STUDENT = ['pickup_points', 'progress', 'reflective_logs'];

    /**
     * The section a file name belongs to, or null if it isn't a bundle CSV.
     */
    public static function sectionFor(string $fileName): ?string
    {
        $section = array_search(strtolower(basename($fileName)), self::FILES, true);

        return $section === false ? null : $section;
    }
}
