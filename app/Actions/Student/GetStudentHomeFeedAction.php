<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Enums\LessonStatus;
use App\Models\Student;
use Illuminate\Support\Carbon;

class GetStudentHomeFeedAction
{
    /**
     * Build the student home page feed payload.
     *
     * Returns an array with: has_instructor, next_lesson, following_lesson,
     * special_offer, purchased_hours, learning_resources, and instructor data.
     *
     * @return array<string, mixed>
     */
    public function __invoke(Student $student): array
    {
        $student->loadMissing([
            'instructor.user:id,name',
            'orders.lessons' => fn ($query) => $query
                ->where('status', '!=', LessonStatus::DRAFT)
                ->where('status', '!=', LessonStatus::CANCELLED),
        ]);

        $upcomingLessons = $this->getUpcomingLessons($student);
        $nextLesson = $upcomingLessons->first();
        $followingLesson = $upcomingLessons->skip(1)->first();

        return [
            'has_instructor' => $student->hasInstructor(),
            'next_lesson' => $nextLesson,
            'following_lesson' => $followingLesson,
            'special_offer' => $student->instructor?->special_offer,
            'purchased_hours' => $this->calculatePurchasedHours($student),
            'learning_resources' => $this->getLearningResources($student),
            'instructor' => $this->getInstructorData($student),
        ];
    }

    /**
     * Get the next two upcoming lessons (today or future, not completed).
     */
    private function getUpcomingLessons(Student $student): \Illuminate\Support\Collection
    {
        $today = Carbon::today();

        return $student->orders
            ->flatMap(fn ($order) => $order->lessons)
            ->filter(fn ($lesson) => $lesson->date && $lesson->date->gte($today) && $lesson->completed_at === null)
            ->sortBy([
                ['date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->take(2)
            ->values();
    }

    /**
     * Calculate total purchased hours as the count of non-draft, non-cancelled lessons.
     */
    private function calculatePurchasedHours(Student $student): int
    {
        return $student->orders
            ->flatMap(fn ($order) => $order->lessons)
            ->count();
    }

    /**
     * Get learning resources that have been assigned to the student's lessons
     * (these are the resources shared during the reflection notes flow).
     */
    private function getLearningResources(Student $student): \Illuminate\Support\Collection
    {
        $lessonIds = $student->orders
            ->flatMap(fn ($order) => $order->lessons)
            ->pluck('id');

        if ($lessonIds->isEmpty()) {
            return collect();
        }

        return \App\Models\Resource::query()
            ->whereHas('lessons', fn ($query) => $query->whereIn('lessons.id', $lessonIds))
            ->published()
            ->distinct()
            ->get();
    }

    /**
     * Get the instructor data including bio for the feed.
     *
     * @return array<string, mixed>|null
     */
    private function getInstructorData(Student $student): ?array
    {
        $instructor = $student->instructor;

        if (! $instructor) {
            return null;
        }

        return [
            'id' => $instructor->id,
            'name' => $instructor->user?->name,
            'bio' => $instructor->bio,
            'profile_picture_url' => $instructor->profile_picture_url,
            'transmission_type' => $instructor->transmission_type,
            'rating' => $instructor->rating,
        ];
    }
}
