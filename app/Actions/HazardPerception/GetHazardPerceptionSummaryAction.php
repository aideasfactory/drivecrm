<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionAttempt;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class GetHazardPerceptionSummaryAction
{
    public function __invoke(Student $student, ?string $category = null): array
    {
        $query = HazardPerceptionAttempt::query()
            ->where('hazard_perception_attempts.student_id', $student->id)
            ->whereNotNull('hazard_perception_attempts.completed_at');

        if ($category) {
            $query->join('hazard_perception_videos', 'hazard_perception_attempts.hazard_perception_video_id', '=', 'hazard_perception_videos.id')
                ->where('hazard_perception_videos.category', $category);
        }

        $stats = (clone $query)->selectRaw('
            COUNT(*) as attempts_taken,
            COALESCE(AVG(hazard_perception_attempts.total_score), 0) as average_score,
            COALESCE(MAX(hazard_perception_attempts.total_score), 0) as best_score
        ')->first();

        $recentAttempts = HazardPerceptionAttempt::query()
            ->where('hazard_perception_attempts.student_id', $student->id)
            ->whereNotNull('hazard_perception_attempts.completed_at')
            ->when($category, function ($q) use ($category): void {
                $q->join('hazard_perception_videos', 'hazard_perception_attempts.hazard_perception_video_id', '=', 'hazard_perception_videos.id')
                    ->where('hazard_perception_videos.category', $category);
            })
            ->select('hazard_perception_attempts.*')
            ->orderByDesc('hazard_perception_attempts.completed_at')
            ->limit(10)
            ->get();

        $topicPerformance = HazardPerceptionAttempt::query()
            ->join('hazard_perception_videos', 'hazard_perception_attempts.hazard_perception_video_id', '=', 'hazard_perception_videos.id')
            ->where('hazard_perception_attempts.student_id', $student->id)
            ->whereNotNull('hazard_perception_attempts.completed_at')
            ->when($category, fn ($q) => $q->where('hazard_perception_videos.category', $category))
            ->groupBy('hazard_perception_videos.topic')
            ->select([
                'hazard_perception_videos.topic',
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('COALESCE(AVG(hazard_perception_attempts.total_score), 0) as average_score'),
            ])
            ->get()
            ->map(fn ($row) => [
                'topic' => $row->topic,
                'total_attempts' => (int) $row->total_attempts,
                'average_score' => round((float) $row->average_score, 1),
            ]);

        return [
            'attempts_taken' => (int) $stats->attempts_taken,
            'average_score' => round((float) $stats->average_score, 1),
            'best_score' => (int) $stats->best_score,
            'recent_attempts' => $recentAttempts,
            'topic_performance' => $topicPerformance,
        ];
    }
}
