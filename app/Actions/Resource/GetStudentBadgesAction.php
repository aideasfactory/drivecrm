<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\HazardPerceptionAttempt;
use App\Models\MockTest;
use App\Models\Resource;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetStudentBadgesAction
{
    /**
     * Compute the badges state for a student.
     *
     * @return array{
     *     first_test: array{earned: bool, earned_at: ?string},
     *     top_score: array{earned: bool, earned_at: ?string},
     *     seven_day_streak: array{earned: bool, earned_at: ?string, current_streak_days: int},
     *     expert: array{earned: bool, earned_at: ?string, criteria: array{perfect_mock: bool, perfect_hazard: bool, all_resources_watched: bool}}
     * }
     */
    public function __invoke(Student $student, User $user): array
    {
        $firstTest = $this->firstTest($student);
        $topScore = $this->topScore($student);
        $streak = $this->sevenDayStreak($student);
        $expert = $this->expert($student, $user);

        return [
            'first_test' => $firstTest,
            'top_score' => $topScore,
            'seven_day_streak' => $streak,
            'expert' => $expert,
        ];
    }

    /**
     * @return array{earned: bool, earned_at: ?string}
     */
    private function firstTest(Student $student): array
    {
        $firstCompleted = MockTest::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->value('completed_at');

        return [
            'earned' => $firstCompleted !== null,
            'earned_at' => $firstCompleted?->toIso8601String(),
        ];
    }

    /**
     * @return array{earned: bool, earned_at: ?string}
     */
    private function topScore(Student $student): array
    {
        $firstPerfect = MockTest::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->whereColumn('correct_answers', 'total_questions')
            ->orderBy('completed_at')
            ->value('completed_at');

        return [
            'earned' => $firstPerfect !== null,
            'earned_at' => $firstPerfect?->toIso8601String(),
        ];
    }

    /**
     * @return array{earned: bool, earned_at: ?string, current_streak_days: int}
     */
    private function sevenDayStreak(Student $student): array
    {
        $days = DB::table('mock_tests')
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->selectRaw('DATE(completed_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->map(fn ($day) => CarbonImmutable::parse($day)->startOfDay())
            ->values();

        if ($days->isEmpty()) {
            return [
                'earned' => false,
                'earned_at' => null,
                'current_streak_days' => 0,
            ];
        }

        $firstSevenDayCompletion = null;
        $runLength = 1;

        for ($i = 1; $i < $days->count(); $i++) {
            $prev = $days[$i - 1];
            $curr = $days[$i];

            if ($curr->equalTo($prev->addDay())) {
                $runLength++;
            } else {
                $runLength = 1;
            }

            if ($runLength === 7 && $firstSevenDayCompletion === null) {
                $firstSevenDayCompletion = $curr;
            }
        }

        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();
        $lastDay = $days->last();

        $currentStreakDays = 0;
        if ($lastDay->equalTo($today) || $lastDay->equalTo($yesterday)) {
            $currentStreakDays = 1;
            for ($i = $days->count() - 1; $i > 0; $i--) {
                if ($days[$i - 1]->equalTo($days[$i]->subDay())) {
                    $currentStreakDays++;
                } else {
                    break;
                }
            }
        }

        return [
            'earned' => $firstSevenDayCompletion !== null,
            'earned_at' => $firstSevenDayCompletion?->toIso8601String(),
            'current_streak_days' => $currentStreakDays,
        ];
    }

    /**
     * @return array{earned: bool, earned_at: ?string, criteria: array{perfect_mock: bool, perfect_hazard: bool, all_resources_watched: bool}}
     */
    private function expert(Student $student, User $user): array
    {
        $perfectMockAt = MockTest::query()
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->whereColumn('correct_answers', 'total_questions')
            ->orderBy('completed_at')
            ->value('completed_at');

        $perfectHazardAt = HazardPerceptionAttempt::query()
            ->where('hazard_perception_attempts.student_id', $student->id)
            ->join('hazard_perception_videos', 'hazard_perception_videos.id', '=', 'hazard_perception_attempts.hazard_perception_video_id')
            ->whereRaw('hazard_perception_attempts.total_score = CASE WHEN hazard_perception_videos.is_double_hazard THEN 10 ELSE 5 END')
            ->orderBy('hazard_perception_attempts.completed_at')
            ->value('hazard_perception_attempts.completed_at');

        $publishedCount = Resource::published()->where('audience', 'student')->count();
        $watchedCount = DB::table('resource_watches')
            ->join('resources', 'resources.id', '=', 'resource_watches.resource_id')
            ->where('resource_watches.user_id', $user->id)
            ->where('resources.status', 'published')
            ->where('resources.audience', 'student')
            ->count();

        $allWatched = $publishedCount > 0 && $watchedCount >= $publishedCount;
        $allWatchedAt = null;
        if ($allWatched) {
            $allWatchedAt = DB::table('resource_watches')
                ->join('resources', 'resources.id', '=', 'resource_watches.resource_id')
                ->where('resource_watches.user_id', $user->id)
                ->where('resources.status', 'published')
                ->where('resources.audience', 'student')
                ->max('resource_watches.created_at');
            $allWatchedAt = $allWatchedAt ? Carbon::parse($allWatchedAt) : null;
        }

        $criteria = [
            'perfect_mock' => $perfectMockAt !== null,
            'perfect_hazard' => $perfectHazardAt !== null,
            'all_resources_watched' => $allWatched,
        ];

        $earned = $criteria['perfect_mock'] && $criteria['perfect_hazard'] && $criteria['all_resources_watched'];
        $earnedAt = null;
        if ($earned) {
            $candidates = array_filter([$perfectMockAt, $perfectHazardAt, $allWatchedAt]);
            $latest = collect($candidates)->sortByDesc(fn ($date) => $date->getTimestamp())->first();
            $earnedAt = $latest?->toIso8601String();
        }

        return [
            'earned' => $earned,
            'earned_at' => $earnedAt,
            'criteria' => $criteria,
        ];
    }
}
