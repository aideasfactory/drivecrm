<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Lesson\SendLessonToRebookAction;
use App\Enums\LessonCancellationReason;
use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class ReturnMissedLessonToPupil extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lessons:return-missed {lesson : Lesson ID the old instructor missed}
        {--by= : User ID of the owner doing this (recorded as who banked it)}
        {--reason=instructor_unavailable : Reason code (e.g. instructor_unavailable, instructor_unwell, other)}
        {--details=Instructor did not attend : Reason details shown on the pupil\'s lesson}
        {--dry-run : Show what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For a pupil transferred after their old instructor missed a lesson: put that lesson on "Rebook later" (same as the diary option — pupil keeps it, payment untouched, no payout) and hand it to the pupil\'s current instructor so they can rebook it in their own diary.';

    /**
     * Execute the console command.
     */
    public function handle(SendLessonToRebookAction $sendLessonToRebook): int
    {
        $lesson = Lesson::with(['order.student.instructor.user', 'instructor.user', 'calendarItem', 'lessonPayment', 'payout'])
            ->find($this->argument('lesson'));

        if (! $lesson) {
            $this->error('Lesson not found.');

            return self::FAILURE;
        }

        $student = $lesson->order?->student;
        $currentInstructor = $student?->instructor;
        $pupil = trim("{$student?->first_name} {$student?->surname}");
        $slot = $lesson->date ? $lesson->date->format('D j M Y').' '.$lesson->start_time?->format('H:i') : 'no date (already on rebook later)';

        $this->line("Lesson #{$lesson->id} — {$pupil}, order #{$lesson->order_id}, {$slot}");
        $this->line('  status: '.$lesson->status->value.', payment: '.($lesson->lessonPayment?->status->value ?? 'none').', payout: '.($lesson->payout ? 'yes' : 'none'));
        $this->line("  taught by (on lesson): {$lesson->instructor?->user?->name} (#{$lesson->instructor_id})");
        $this->line("  pupil's instructor now: {$currentInstructor?->user?->name} (#{$currentInstructor?->id})");

        $problem = match (true) {
            $lesson->status !== LessonStatus::PENDING => 'Only a pending lesson can be returned (this one is '.$lesson->status->value.').',
            $lesson->payout !== null => 'This lesson has already been paid out.',
            $currentInstructor === null => 'The pupil has no current instructor.',
            ! $lesson->isBanked() && ! $lesson->calendarItem => 'The lesson has no diary slot to take it off.',
            $lesson->isBanked() && $lesson->instructor_id === $currentInstructor->id => 'Nothing to do: already on rebook later with the current instructor.',
            default => null,
        };

        if ($problem !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        $reason = LessonCancellationReason::tryFrom((string) $this->option('reason'));

        if (! $reason || $reason === LessonCancellationReason::BankedExpired) {
            $this->error('Unknown --reason. Use one of: instructor_unavailable, instructor_unwell, vehicle_problem, weather, other, …');

            return self::FAILURE;
        }

        $actor = User::find($this->option('by'));

        if (! $actor?->isOwner()) {
            $this->error('Pass --by=<user id> of an owner.');

            return self::FAILURE;
        }

        $this->line('Will:');
        if (! $lesson->isBanked()) {
            $this->line("  1. Put it on \"Rebook later\" ({$reason->describe($this->option('details'))}) — pupil keeps it, payment unchanged, emails the pupil");
        }
        $this->line("  2. Move it to {$currentInstructor->user?->name} so they can rebook it from their diary");

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing was saved.');

            return self::SUCCESS;
        }

        try {
            $sendLessonToRebook($lesson, $reason, $this->option('details'), $currentInstructor, $actor);
        } catch (Throwable $e) {
            $this->error("Couldn't put it on rebook later: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Done. {$currentInstructor->user?->name} can now rebook lesson #{$lesson->id} from the pupil's \"To rebook\" list.");

        return self::SUCCESS;
    }
}
