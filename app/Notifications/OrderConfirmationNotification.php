<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Order;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Order $order,
        public Student $student,
        public bool $isBookedByContact
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $instructor = $order->instructor;
        $firstLesson = $order->lessons()->orderBy('date')->first();

        $firstLessonLine = $firstLesson
            ? 'First lesson: '.Carbon::parse($firstLesson->date)->format('l, F j, Y')
            : '';

        $bookedForLine = '';
        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;
            $bookedForLine = "\nThis booking was made for: **{$learnerName}**";
        }

        $message = $this->templatedMail(
            EmailTemplateKey::LearnerOrderConfirmation,
            [
                'recipient_name' => $this->recipientName(),
                'intro' => $this->introLine(),
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'instructor_name' => $instructor->user->name,
                'first_lesson_line' => $firstLessonLine,
                'payment_block' => $this->paymentBlock(),
                'booked_for_line' => $bookedForLine,
                'app_name' => config('app.name'),
            ],
            url('/get-app'),
        );

        $storeButtons = $this->appStoreButtons();

        if ($storeButtons !== null) {
            $message->line($storeButtons);
        }

        return $message;
    }

    /**
     * App Store / Google Play buttons, limited to the store links that are configured.
     */
    protected function appStoreButtons(): ?HtmlString
    {
        $links = array_filter([
            'Download on the App Store' => config('app_links.apple') ?: null,
            'Get it on Google Play' => config('app_links.android') ?: null,
        ]);

        if ($links === []) {
            return null;
        }

        $buttons = collect($links)
            ->map(fn (string $url, string $label): string => sprintf(
                '<a href="%s" target="_blank" rel="noopener" style="display:inline-block;margin:4px;padding:10px 18px;background:#111111;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;">%s</a>',
                e($url),
                e($label),
            ))
            ->implode('');

        return new HtmlString('<p style="text-align:center;margin:8px 0 4px;"><strong>Get the DRIVE app</strong></p><p style="text-align:center;">'.$buttons.'</p>');
    }

    protected function recipientName(): string
    {
        if ($this->isBookedByContact) {
            return $this->student->contact_first_name ?? 'there';
        }

        return $this->student->first_name ?? 'there';
    }

    protected function introLine(): string
    {
        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;

            return "Great news! You have successfully booked driving lessons for **{$learnerName}**.";
        }

        return 'Great news! Your driving lessons have been successfully booked.';
    }

    protected function paymentBlock(): string
    {
        $order = $this->order;

        if ($order->isUpfront()) {
            return implode("\n", [
                '**Payment — paid in full:**',
                ...$order->costBreakdownLines(),
                "**Total paid: {$order->formatted_amount_paid}**",
            ]);
        }

        if (! $order->hasFees()) {
            return "Payment: Weekly ({$order->formatted_weekly_instalment} per lesson)";
        }

        $hasPaidGuarantee = $order->includes_test_pass_guarantee && $order->test_pass_guarantee_pence > 0;

        return implode("\n", array_filter([
            '**Payment — weekly:**',
            ...$order->costBreakdownLines(),
            "**Total: {$order->formatted_amount_paid}**",
            "Paid in {$order->package_lessons_count} weekly instalments of {$order->formatted_weekly_instalment}",
            $hasPaidGuarantee ? '(Pass Your Test Guarantee added to your first weekly payment)' : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'package_name' => $this->order->package_name,
            'lessons_count' => $this->order->package_lessons_count,
        ];
    }
}
