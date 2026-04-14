<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;
use App\Models\ResourceFolder;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetResourceSummaryAction
{
    private const STUDY_TIPS = [
        'Practice hazard perception tests regularly. The more scenarios you see, the better you\'ll recognise potential dangers on the road.',
        'Review the Highway Code for at least 15 minutes each day rather than cramming before your theory test.',
        'When practising manoeuvres, focus on your reference points and mirrors rather than rushing through the exercise.',
        'Watch each video resource twice — once to understand the concept and a second time to focus on the details.',
        'After each driving lesson, write down one thing you did well and one thing to improve. This builds self-awareness.',
        'Study road signs in groups — warning signs, regulatory signs, and informational signs have distinct shapes and colours.',
        'Before your next lesson, watch the related video resource so you arrive prepared and can focus on practical application.',
        'Use the "commentary driving" technique — describe hazards and decisions out loud while a passenger. It sharpens observation.',
        'Practise the MSM routine (Mirror, Signal, Manoeuvre) mentally when you\'re a passenger in someone else\'s car.',
        'Focus on understanding why rules exist, not just memorising them. Understanding makes recall easier under pressure.',
        'Take mock theory tests under timed conditions to build confidence with the exam format.',
        'Review your incorrect answers on mock tests carefully — they highlight the specific areas where you need more study.',
        'When learning roundabouts, focus on lane discipline first. The correct lane matters more than speed.',
        'Practise emergency stop scenarios mentally — knowing the procedure builds muscle memory before you need it.',
        'Study in short, focused sessions of 20-30 minutes. Your brain retains more from multiple short sessions than one long one.',
        'Revisit resources your instructor has suggested — they\'re tailored to what came up in your lessons.',
        'Pay attention to road markings when you\'re a passenger. Understanding what they mean will help during your driving test.',
        'Before approaching a junction, remember the LADA routine: Look, Assess, Decide, Act.',
        'Night driving requires extra caution — review the guidance on headlight usage and speed adjustment in low visibility.',
        'Parallel parking becomes easier when you consistently use the same reference points. Find yours and practise until they\'re automatic.',
    ];

    /**
     * Get the full resource summary for a student's dashboard.
     *
     * @return array{recent_activity: Collection, stats: array, study_progress: Collection, recommended: Collection, study_tip: string}
     */
    public function __invoke(Student $student, User $user): array
    {
        return [
            'recent_activity' => $this->getRecentActivity($user),
            'stats' => $this->getStats($user),
            'study_progress' => $this->getStudyProgress($user),
            'recommended' => $this->getRecommended($student, $user),
            'study_tip' => self::STUDY_TIPS[array_rand(self::STUDY_TIPS)],
        ];
    }

    /**
     * Get the student's most recent resource interactions.
     */
    private function getRecentActivity(User $user): Collection
    {
        return DB::table('resource_watches')
            ->join('resources', 'resources.id', '=', 'resource_watches.resource_id')
            ->where('resource_watches.user_id', $user->id)
            ->where('resources.status', 'published')
            ->select([
                'resources.id',
                'resources.title',
                'resources.resource_type',
                'resource_watches.created_at as watched_at',
            ])
            ->orderByDesc('resource_watches.created_at')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->resource_type === 'video_link' ? 'video' : 'file',
                'watched_at' => $item->watched_at,
            ]);
    }

    /**
     * Get aggregate stats for the student.
     */
    private function getStats(User $user): array
    {
        $totalResources = Resource::published()->count();

        $watchCounts = DB::table('resource_watches')
            ->join('resources', 'resources.id', '=', 'resource_watches.resource_id')
            ->where('resource_watches.user_id', $user->id)
            ->where('resources.status', 'published')
            ->select(DB::raw("
                SUM(CASE WHEN resources.resource_type = 'video_link' THEN 1 ELSE 0 END) as videos_watched,
                SUM(CASE WHEN resources.resource_type = 'file' THEN 1 ELSE 0 END) as files_opened
            "))
            ->first();

        return [
            'total_resources' => $totalResources,
            'videos_watched' => (int) ($watchCounts->videos_watched ?? 0),
            'files_opened' => (int) ($watchCounts->files_opened ?? 0),
            'mock_test_average' => '41/50',
            'mock_test_percentage' => 82,
            'hazard_perception_average' => '38/50',
            'hazard_perception_percentage' => 76,
        ];
    }

    /**
     * Get per-folder study progress for top-level folders.
     */
    private function getStudyProgress(User $user): Collection
    {
        // Get all top-level folders with their resources and children's resources
        $folders = ResourceFolder::query()
            ->whereNull('parent_id')
            ->with([
                'resources' => fn ($q) => $q->published(),
                'children.resources' => fn ($q) => $q->published(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $watchedIds = DB::table('resource_watches')
            ->where('user_id', $user->id)
            ->pluck('resource_id');

        return $folders
            ->map(function (ResourceFolder $folder) use ($watchedIds) {
                // Collect all resource IDs in this folder and its children
                $resourceIds = $folder->resources->pluck('id');
                foreach ($folder->children as $child) {
                    $resourceIds = $resourceIds->merge($child->resources->pluck('id'));
                }

                $total = $resourceIds->count();

                if ($total === 0) {
                    return null;
                }

                $watched = $resourceIds->intersect($watchedIds)->count();

                return [
                    'folder_name' => $folder->name,
                    'total' => $total,
                    'watched' => $watched,
                    'percentage' => (int) floor(($watched / $total) * 100),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Get recommended resources from lesson sign-offs, prioritising unwatched.
     */
    private function getRecommended(Student $student, User $user): Collection
    {
        $watchedIds = DB::table('resource_watches')
            ->where('user_id', $user->id)
            ->pluck('resource_id');

        return DB::table('lesson_resource')
            ->join('lessons', 'lessons.id', '=', 'lesson_resource.lesson_id')
            ->join('orders', 'orders.id', '=', 'lessons.order_id')
            ->join('resources', 'resources.id', '=', 'lesson_resource.resource_id')
            ->leftJoin('resource_folders', 'resource_folders.id', '=', 'resources.resource_folder_id')
            ->where('orders.student_id', $student->id)
            ->where('resources.status', 'published')
            ->select([
                'resources.id',
                'resources.title',
                'resources.resource_type',
                'resources.thumbnail_url',
                'resource_folders.name as folder_name',
            ])
            ->distinct()
            ->get()
            ->sortBy(fn ($r) => $watchedIds->contains($r->id) ? 1 : 0)
            ->take(5)
            ->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'resource_type' => $r->resource_type,
                'thumbnail_url' => $r->thumbnail_url,
                'folder_name' => $r->folder_name,
            ])
            ->values();
    }
}
