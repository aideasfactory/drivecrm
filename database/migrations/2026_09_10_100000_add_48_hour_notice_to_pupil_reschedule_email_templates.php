<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace unmodified default bodies with copy that states the 48-hour
     * reschedule notice. Staff-edited rows are left untouched.
     *
     * @return array<string, array{old: string, new: string}>
     */
    private function bodies(): array
    {
        return [
            'learner.order_confirmation' => [
                'old' => "{{intro}}\n\n**Order Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n{{first_lesson_line}}\n\n{{payment_block}}\n\n**Next Steps:**\n1. Download the app to view your lesson schedule\n2. Your instructor will contact you to confirm the details\n3. Make sure to arrive 5 minutes early for your first lesson\n{{booked_for_line}}\n\n{{action_button}}\n\nThank you for choosing us for your driving lessons!",
                'new' => "{{intro}}\n\n**Order Details:**\nPackage: {{package_name}}\nNumber of lessons: {{lessons_count}}\nInstructor: {{instructor_name}}\n{{first_lesson_line}}\n\n{{payment_block}}\n\n**Next Steps:**\n1. Download the app to view your lesson schedule\n2. Your instructor will contact you to confirm the details\n3. Make sure to arrive 5 minutes early for your first lesson\n4. Please give at least 48 hours' notice if you need to reschedule a lesson\n{{booked_for_line}}\n\n{{action_button}}\n\nThank you for choosing us for your driving lessons!",
            ],
            'learner.lessons_bulk_rescheduled' => [
                'old' => "Your upcoming {{lesson_word}} with **{{instructor_name}}** have been rescheduled.\n\nFrom **{{start_date}}**, you will now have your lessons on **{{day_of_week}}s at {{time}}**.\n\nTotal {{lesson_word}} moved: **{{total_lessons}}**.\n\nIf this new schedule does not work for you, please contact your instructor to arrange alternatives.",
                'new' => "Your upcoming {{lesson_word}} with **{{instructor_name}}** have been rescheduled.\n\nFrom **{{start_date}}**, you will now have your lessons on **{{day_of_week}}s at {{time}}**.\n\nTotal {{lesson_word}} moved: **{{total_lessons}}**.\n\nPlease give at least 48 hours' notice if you need to reschedule a lesson. If this new schedule does not work for you, please contact your instructor to arrange alternatives.",
            ],
            'learner.lesson_rescheduled' => [
                'old' => "Your driving lesson with **{{instructor_name}}** has been rescheduled.\n\n**Previous:**\n{{old_when}}\n\n**New:**\n{{new_when}}\n{{notes_block}}\n\nIf this new time does not work for you, please contact your instructor to arrange an alternative.",
                'new' => "Your driving lesson with **{{instructor_name}}** has been rescheduled.\n\n**Previous:**\n{{old_when}}\n\n**New:**\n{{new_when}}\n{{notes_block}}\n\nPlease give at least 48 hours' notice if you need to reschedule a lesson. If this new time does not work for you, please contact your instructor to arrange an alternative.",
            ],
            'learner.lesson_payment_received' => [
                'old' => "{{intro}}\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nInstructor: {{instructor_name}}\nAmount paid: {{amount}}\n\nYour lesson is confirmed. Please arrive 5 minutes early.\nIf you need to make any changes, please contact us as soon as possible.",
                'new' => "{{intro}}\n\n**Lesson Details:**\nPackage: {{package_name}}\nDate: {{lesson_date}}\nTime: {{lesson_time}}\nInstructor: {{instructor_name}}\nAmount paid: {{amount}}\n\nYour lesson is confirmed. Please arrive 5 minutes early.\nPlease give at least 48 hours' notice if you need to reschedule a lesson, and contact us as soon as possible.",
            ],
        ];
    }

    public function up(): void
    {
        $this->swapBodies('old', 'new');
    }

    public function down(): void
    {
        $this->swapBodies('new', 'old');
    }

    private function swapBodies(string $from, string $to): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->bodies() as $key => $bodies) {
            DB::table('email_templates')
                ->where('key', $key)
                ->where('body', $bodies[$from])
                ->update([
                    'body' => $bodies[$to],
                    'updated_at' => now(),
                ]);
        }
    }
};
