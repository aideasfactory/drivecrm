<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\ProgressTracker\SeedInstructorProgressTrackerAction;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use App\Models\Student;
use App\Models\StudentProgress;

class ImportStagedProgressAction
{
    public function __construct(
        protected ReadStagedRowsAction $readStagedRows,
        protected SeedInstructorProgressTrackerAction $seedInstructorProgressTracker,
    ) {}

    /**
     * Stage 5 for one instructor ref (rows grouped by the pupil's
     * instructor): each pupil's latest score per skill, converted to Drive's
     * 1–5 scale, scored against their current instructor's framework.
     *
     * Skills are matched by category + skill name (case-insensitive); a
     * missing category or skill is added to that instructor's framework. A
     * score is only added where the pupil has none for that skill — a score
     * already in Drive is never changed.
     *
     * @return array<string, int>
     */
    public function __invoke(ImportRun $run, string $instructorRef): array
    {
        $counts = ['scores_added' => 0, 'scores_already_in_drive' => 0, 'already_imported' => 0, 'skills_added_to_framework' => 0, 'skipped' => 0];

        /** @var array<string, array<string, array{category: string, skill: string, score: int, date: string}>> $latest student_ref => skill key => latest */
        $latest = [];

        foreach (($this->readStagedRows)($run, 'progress', $instructorRef) as ['row' => $row]) {
            $score = self::convertScore((string) $row['score'], (int) $row['scale_max']);

            if ($score === null) {
                $counts['skipped']++;

                continue;
            }

            $key = CheckStagedImportAction::progressRef((string) $row['student_ref'], (string) $row['category'], (string) $row['skill']);
            $date = (string) ($row['date_scored'] ?? '');
            $current = $latest[$row['student_ref']][$key] ?? null;

            if ($current === null || $date >= $current['date']) {
                $latest[$row['student_ref']][$key] = ['category' => trim($row['category']), 'skill' => trim($row['skill']), 'score' => $score, 'date' => $date];
            }
        }

        $studentIds = ImportMapping::modelIdsFor(ImportMapping::ENTITY_STUDENT, array_keys($latest));
        /** @var array<int, array<string, ProgressSubcategory>> $frameworks instructor id => "category|skill" => subcategory */
        $frameworks = [];

        foreach ($latest as $studentRef => $skills) {
            $student = isset($studentIds[$studentRef]) ? Student::query()->find($studentIds[$studentRef]) : null;
            $instructor = $student?->instructor_id !== null ? Instructor::query()->find($student->instructor_id) : null;

            if ($student === null || $instructor === null) {
                $counts['skipped'] += count($skills);

                continue;
            }

            $mapped = ImportMapping::modelIdsFor(ImportMapping::ENTITY_PROGRESS, array_keys($skills));
            $frameworks[$instructor->id] ??= $this->framework($instructor);

            foreach ($skills as $ref => $skill) {
                if (isset($mapped[$ref])) {
                    $counts['already_imported']++;

                    continue;
                }

                $subcategory = $this->subcategory($instructor, $frameworks[$instructor->id], $skill['category'], $skill['skill'], $counts);

                // The instructor deleted this skill or its category: not brought back.
                if ($subcategory === null) {
                    $counts['skipped']++;

                    continue;
                }

                if (StudentProgress::query()->where('student_id', $student->id)->where('progress_subcategory_id', $subcategory->id)->exists()) {
                    $counts['scores_already_in_drive']++;

                    continue;
                }

                $progress = StudentProgress::create([
                    'student_id' => $student->id,
                    'progress_subcategory_id' => $subcategory->id,
                    'score' => $skill['score'],
                ]);

                ImportMapping::record($run, ImportMapping::ENTITY_PROGRESS, $ref, $progress->id);
                $counts['scores_added']++;
            }
        }

        return $counts;
    }

    /**
     * A legacy score on a 3-, 5- or 10-point scale as Drive's 1–5
     * (1 Introduced, 2 Instructed, 3 Prompted, 4 Seldom prompted,
     * 5 Independent), matched by the scales' labels. Null when out of range.
     */
    public static function convertScore(string $score, int $scale): ?int
    {
        if (! ctype_digit(trim($score))) {
            return null;
        }

        $value = (int) $score;

        if ($value < 1 || $value > $scale) {
            return null;
        }

        return match ($scale) {
            5 => $value,
            3 => [1 => 1, 2 => 3, 3 => 5][$value],
            10 => match (true) {
                $value === 1 => 1,
                $value <= 4 => 2,
                $value <= 7 => 3,
                $value <= 9 => 4,
                default => 5,
            },
            default => null,
        };
    }

    /**
     * The instructor's current framework (seeded first if they have none).
     *
     * @return array<string, ProgressSubcategory> "category|skill" (lower-case) => subcategory
     */
    protected function framework(Instructor $instructor): array
    {
        ($this->seedInstructorProgressTracker)($instructor);

        $framework = [];

        ProgressCategory::query()
            ->where('instructor_id', $instructor->id)
            ->with('subcategories')
            ->get()
            ->each(function (ProgressCategory $category) use (&$framework): void {
                foreach ($category->subcategories as $subcategory) {
                    $framework[$this->frameworkKey($category->name, $subcategory->name)] = $subcategory;
                }
            });

        return $framework;
    }

    /**
     * The instructor's skill for a category + skill name, added when missing.
     * Null when the instructor deleted that skill or its category (a deleted
     * one is never re-created).
     *
     * @param  array<string, ProgressSubcategory|null>  $framework
     * @param  array<string, int>  $counts
     */
    protected function subcategory(Instructor $instructor, array &$framework, string $categoryName, string $skillName, array &$counts): ?ProgressSubcategory
    {
        $key = $this->frameworkKey($categoryName, $skillName);

        if (array_key_exists($key, $framework)) {
            return $framework[$key];
        }

        $categoryQuery = fn () => ProgressCategory::query()
            ->where('instructor_id', $instructor->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(mb_substr($categoryName, 0, 100))]);

        $category = $categoryQuery()->first();

        if ($category === null && $categoryQuery()->onlyTrashed()->exists()) {
            return $framework[$key] = null;
        }

        if ($category !== null && ProgressSubcategory::onlyTrashed()
            ->where('progress_category_id', $category->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(mb_substr($skillName, 0, 100))])
            ->exists()) {
            return $framework[$key] = null;
        }

        $category ??= ProgressCategory::create([
            'instructor_id' => $instructor->id,
            'name' => mb_substr($categoryName, 0, 100),
            'sort_order' => (int) ProgressCategory::query()->where('instructor_id', $instructor->id)->max('sort_order') + 1,
        ]);

        $subcategory = ProgressSubcategory::create([
            'progress_category_id' => $category->id,
            'name' => mb_substr($skillName, 0, 100),
            'sort_order' => (int) ProgressSubcategory::query()->where('progress_category_id', $category->id)->max('sort_order') + 1,
        ]);

        $counts['skills_added_to_framework']++;

        return $framework[$key] = $subcategory;
    }

    protected function frameworkKey(string $category, string $skill): string
    {
        return mb_strtolower(trim(mb_substr($category, 0, 100))).'|'.mb_strtolower(trim(mb_substr($skill, 0, 100)));
    }
}
