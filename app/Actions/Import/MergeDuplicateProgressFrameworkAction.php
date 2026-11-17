<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ProgressCategory;
use App\Models\ProgressSubcategory;
use App\Models\StudentProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergeDuplicateProgressFrameworkAction
{
    /**
     * Merge progress categories (per instructor) and skills (per category)
     * that exist twice under the same name — left by two import jobs adding
     * the same instructor's framework at once. The oldest (lowest id) is kept.
     *
     * A duplicate skill's scores move to the kept skill of the same name;
     * where the pupil already has a score there, that score stays and the
     * duplicate's is removed. A duplicate category's skill with no
     * same-name match moves to the kept category. Emptied duplicates are
     * soft-deleted.
     *
     * @return array{categories_merged: int, skills_merged: int, skills_moved: int, scores_moved: int, scores_dropped: int, instructors: int}
     */
    public function __invoke(bool $dryRun): array
    {
        $counts = ['categories_merged' => 0, 'skills_merged' => 0, 'skills_moved' => 0, 'scores_moved' => 0, 'scores_dropped' => 0, 'instructors' => 0];
        /** @var array<int, true> $instructors */
        $instructors = [];

        foreach ($this->duplicateCategoryGroups() as $group) {
            $keep = $group->shift();
            $instructors[$keep->instructor_id] = true;

            foreach ($group as $duplicate) {
                $this->runStep($dryRun, function () use ($keep, $duplicate, $dryRun, &$counts): void {
                    foreach ($duplicate->subcategories()->orderBy('id')->get() as $skill) {
                        $match = $keep->subcategories()->where('name', $skill->name)->orderBy('id')->first();

                        if ($match === null) {
                            $counts['skills_moved']++;

                            if (! $dryRun) {
                                $skill->update(['progress_category_id' => $keep->id]);
                            }

                            continue;
                        }

                        $this->mergeSkill($match, $skill, $dryRun, $counts);
                    }

                    $counts['categories_merged']++;

                    if (! $dryRun) {
                        $duplicate->delete();
                    }
                });
            }
        }

        foreach ($this->duplicateSkillGroups() as $group) {
            $keep = $group->shift();
            $instructors[(int) $keep->category?->instructor_id] = true;

            foreach ($group as $duplicate) {
                $this->runStep($dryRun, fn () => $this->mergeSkill($keep, $duplicate, $dryRun, $counts));
            }
        }

        $counts['instructors'] = count($instructors);

        if (! $dryRun && ($counts['categories_merged'] + $counts['skills_merged']) > 0) {
            Log::info('import:repair merged duplicate progress framework entries', $counts);
        }

        return $counts;
    }

    /**
     * Move a duplicate skill's scores onto the kept skill, then soft-delete
     * the duplicate. A pupil already scored on the kept skill keeps that score.
     *
     * @param  array<string, int>  $counts
     */
    protected function mergeSkill(ProgressSubcategory $keep, ProgressSubcategory $duplicate, bool $dryRun, array &$counts): void
    {
        $alreadyScored = StudentProgress::query()
            ->where('progress_subcategory_id', $keep->id)
            ->pluck('student_id')
            ->all();

        $scores = StudentProgress::query()->where('progress_subcategory_id', $duplicate->id);
        $dropped = (clone $scores)->whereIn('student_id', $alreadyScored)->count();
        $moved = (clone $scores)->count() - $dropped;

        $counts['scores_moved'] += $moved;
        $counts['scores_dropped'] += $dropped;
        $counts['skills_merged']++;

        if ($dryRun) {
            return;
        }

        (clone $scores)->whereIn('student_id', $alreadyScored)->delete();
        (clone $scores)->update(['progress_subcategory_id' => $keep->id]);
        $duplicate->delete();
    }

    protected function runStep(bool $dryRun, callable $step): void
    {
        $dryRun ? $step() : DB::transaction($step);
    }

    /**
     * Active categories sharing an instructor and name, oldest first.
     *
     * @return array<int, Collection<int, ProgressCategory>>
     */
    protected function duplicateCategoryGroups(): array
    {
        $keys = ProgressCategory::query()
            ->select('instructor_id', 'name')
            ->groupBy('instructor_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $keys->map(fn ($key) => ProgressCategory::query()
            ->where('instructor_id', $key->instructor_id)
            ->where('name', $key->name)
            ->orderBy('id')
            ->get())
            ->all();
    }

    /**
     * Active skills sharing a category and name, oldest first.
     *
     * @return array<int, Collection<int, ProgressSubcategory>>
     */
    protected function duplicateSkillGroups(): array
    {
        $keys = ProgressSubcategory::query()
            ->select('progress_category_id', 'name')
            ->groupBy('progress_category_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $keys->map(fn ($key) => ProgressSubcategory::query()
            ->with('category')
            ->where('progress_category_id', $key->progress_category_id)
            ->where('name', $key->name)
            ->orderBy('id')
            ->get())
            ->all();
    }
}
