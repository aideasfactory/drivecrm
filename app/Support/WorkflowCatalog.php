<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EmailTemplateKey;

/**
 * Plain-English map of every journey from first enquiry to last lesson, shown
 * to owners on the Workflows page. Keep this in step with the behaviour it
 * describes; the developer reference with code paths is `docs/workflows.md`.
 *
 * Emails reference an {@see EmailTemplateKey} so the page can show the
 * template's admin name and open it in the editor. Timings are read from
 * config so they always match what the system does.
 *
 * @phpstan-type WorkflowMessage array{channel: 'email'|'push'|'admin_email'|'bird'|'none', template: ?string, label: string, recipient: string, note: ?string}
 * @phpstan-type WorkflowStep array{when: string, what: string, messages: list<WorkflowMessage>}
 * @phpstan-type Workflow array{key: string, group: string, title: string, summary: string, steps: list<WorkflowStep>}
 */
final class WorkflowCatalog
{
    public const GROUP_ENQUIRIES = 'Enquiries';

    public const GROUP_BOOKING = 'Booking & payment';

    public const GROUP_LESSONS = 'Lessons';

    public const GROUP_CHANGES = 'Changes & cancellations';

    public const GROUP_INSTRUCTORS = 'Instructors';

    public const GROUP_ACCOUNTS = 'Accounts & messages';

    /**
     * @return list<Workflow>
     */
    public static function workflows(): array
    {
        return [
            ...self::enquiryWorkflows(),
            ...self::bookingWorkflows(),
            ...self::lessonWorkflows(),
            ...self::changeWorkflows(),
            ...self::instructorWorkflows(),
            ...self::accountWorkflows(),
        ];
    }

    /**
     * @return list<Workflow>
     */
    private static function enquiryWorkflows(): array
    {
        return [
            self::workflow('enquiry-quick-form', self::GROUP_ENQUIRIES, 'Enquiry through the quick booking form', 'A learner leaves their details on the short booking form. Head office is told straight away and the contact is added to Bird.', [
                self::step('The learner fills in the form', 'They give their name, phone number, email, postcode and whether they want manual or automatic lessons.', [
                    self::none('Nothing is sent to the learner.'),
                ]),
                self::step('Straight after they submit', 'The system checks whether the instructor for that transmission covers the postcode, and labels the enquiry "In area" or "Out of area".', [
                    self::adminEmail('New booking enquiry, marked [In area] or [Out of area]', 'Head office booking inbox'),
                    self::bird('Contact added or updated in Bird, including whether an instructor is available. Any SMS or WhatsApp follow-up is run by Bird automations.'),
                ]),
                self::step('The learner sees the result', 'In area: they are invited to continue. Out of area: they are offered a nearby postcode, a number to call, and "We\'ll notify you when we expand".', [
                    self::none('Nothing is sent to the learner. Your team follows up by phone or through Bird.'),
                ]),
                self::step('Head office reviews enquiries', 'Every enquiry appears on the Enquiries page, showing how far the learner got and whether they are in area.', []),
            ]),

            self::workflow('enquiry-no-instructor', self::GROUP_ENQUIRIES, 'Enquiry where no instructor is available', 'What happens when nobody covers the learner\'s postcode. The quick form and the full online booking behave differently.', [
                self::step('Quick booking form: postcode is out of area', 'The enquiry is labelled "Out of area" and the learner is shown "Try a nearby postcode", "Call us" and "We\'ll notify you when we expand".', [
                    self::adminEmail('New booking enquiry, marked [Out of area]', 'Head office booking inbox'),
                    self::bird('Contact added to Bird with "instructor available: No".'),
                ]),
                self::step('Later, when you expand', 'Nothing in the system notifies these learners. Any follow-up has to come from Bird or from your team.', [
                    self::none('Nothing is sent by the system.'),
                ]),
                self::step('Full online booking: no instructor within '.config('services.instructor.search_radius_miles', 10).' miles', 'The learner sees "No instructors cover your area yet" with options to change postcode or email you.', [
                    self::none('Nothing is sent to anyone and the contact is not added to Bird. The enquiry only appears on the Enquiries page.'),
                ]),
            ]),
        ];
    }

    /**
     * @return list<Workflow>
     */
    private static function bookingWorkflows(): array
    {
        $holdMinutes = BookingPayments::learnerHoldMinutes();
        $instructorHoldHours = (int) config('booking_payments.instructor_hold_hours_before_lesson', 48);
        $minimumHoldMinutes = (int) config('booking_payments.minimum_hold_minutes', 15);
        $weeklyDueHours = BookingPayments::weeklyPaymentDueHoursBeforeLesson();
        $guaranteeFreeHours = (float) config('test_pass_guarantee.free_minimum_hours', 10);
        $guaranteePrice = '£'.number_format((float) config('test_pass_guarantee.price', 50), 2);

        $paidMessages = [
            self::email(EmailTemplateKey::LearnerOrderConfirmation, 'Learner, or the person who booked'),
            self::email(EmailTemplateKey::LearnerWelcome, 'Learner', 'Only the first time they pay. Includes their login details.'),
            self::email(EmailTemplateKey::LearnerLessonPaymentReceived, 'Learner, or the person who booked'),
            self::email(EmailTemplateKey::InstructorLessonPaymentReceived, 'Instructor'),
            self::push('Lesson paid', 'Instructor'),
        ];

        return [
            self::workflow('booking-pay-in-full', self::GROUP_BOOKING, 'Learner books online and pays in full', 'The learner chooses an instructor, package and first lesson online, then pays for the whole package.', [
                self::step('The learner chooses their lessons', 'They pick an instructor, a package and a first lesson slot, then enter their details.', [
                    self::none('Nothing is sent yet.'),
                ]),
                self::step('They click to pay', "Their account is created and the lessons are held for {$holdMinutes} minutes while they pay on the secure Stripe checkout page.", [
                    self::none('Nothing is sent yet.'),
                ]),
                self::step('Payment goes through', "The booking is confirmed, the lessons go into the instructor's diary and the learner is linked to the instructor. Packages of {$guaranteeFreeHours} hours or more paid in full include the Pass Your Test Guarantee free.", $paidMessages),
                self::step('If they do not pay in time', 'The lessons are released. See "Booking not paid in time".', []),
            ]),

            self::workflow('booking-weekly', self::GROUP_BOOKING, 'Learner books online and pays weekly', 'The learner pays for the first week at checkout, then pays for each following lesson by invoice before it takes place.', [
                self::step('The learner books and pays for the first week', "Same as paying in full, except only the first week is taken at checkout. A Pass Your Test Guarantee on a weekly plan costs {$guaranteePrice}, added to the first payment.", $paidMessages),
                self::step('Invoice for the next lesson', "As soon as the previous lesson is signed off (or by an hourly check as the due date approaches), the next lesson is invoiced. It is due {$weeklyDueHours} hours before the lesson starts.", [
                    self::email(EmailTemplateKey::LearnerLessonPaymentReminder, 'Learner, or the person who booked'),
                    self::push('Time to pay for your lesson', 'Learner'),
                ]),
                self::step('48 hours before an unpaid lesson', 'If the lesson still has not been paid, a reminder goes out.', [
                    self::email(EmailTemplateKey::LearnerPaymentDueSoon, 'Learner, or the person who booked'),
                    self::push('Payment reminder', 'Learner', 'Only if the learner has their own account in the app.'),
                ]),
                self::step('The lesson is paid', 'The payment is recorded and the lesson is confirmed in the diary.', [
                    self::email(EmailTemplateKey::LearnerLessonPaymentReceived, 'Learner, or the person who booked'),
                    self::email(EmailTemplateKey::InstructorLessonPaymentReceived, 'Instructor'),
                    self::push('Lesson paid', 'Instructor'),
                ]),
                self::step('The lesson is still unpaid when it is due', 'No further action is taken. The instructor cannot sign off a lesson that has not been paid.', [
                    self::none('Nothing is sent.'),
                ]),
            ]),

            self::workflow('booking-by-instructor', self::GROUP_BOOKING, 'Instructor or head office books lessons for a learner', 'An instructor (in the app or CRM) or head office books lessons for a learner, who is then sent a link to pay.', [
                self::step('The booking is made', 'The lessons are added to the diary, waiting for payment. If they overlap something already in the diary, the instructor is warned.', [
                    self::email(EmailTemplateKey::LearnerPaymentLink, 'Learner, or the person paying'),
                    self::email(EmailTemplateKey::InstructorCalendarClash, 'Instructor', 'Only when the new lessons overlap existing diary items.'),
                ]),
                self::step('How long the lessons are held', "Instructor bookings are held until {$instructorHoldHours} hours before the first lesson. Head office bookings are held until midnight (UK time) on the day they are made. Either way, the hold is at least {$minimumHoldMinutes} minutes.", []),
                self::step('The payment link is re-sent', 'The instructor can re-send the link (at most once every 3 minutes).', [
                    self::email(EmailTemplateKey::LearnerPaymentLink, 'Learner, or the person paying'),
                    self::push('Payment link re-sent', 'Learner', 'Instructor bookings only, and only if the learner has their own account in the app.'),
                ]),
                self::step('The learner pays', 'Everything from here is the same as a learner paying in full or weekly online.', $paidMessages),
                self::step('If they do not pay in time', 'The lessons are released. See "Booking not paid in time".', []),
            ]),

            self::workflow('booking-cash', self::GROUP_BOOKING, 'Imported learner pays in cash', 'Head office books lessons for a learner imported from another system, who pays the instructor in cash at each lesson. Cash payments are off for every learner, including straight after import, until an owner with full admin access turns them on from the pupil page. Nothing goes through Stripe and there is no booking or digital fee.', [
                self::step('Head office books the lessons', 'An owner with full admin access picks "Pay in cash" when booking from the pupil page, and can add a note. The booking team and instructors cannot. The booking is confirmed straight away, with no payment window, and the lessons go into the diary marked "Cash".', [
                    self::email(EmailTemplateKey::LearnerCashBookingConfirmed, 'Learner, or the person who booked'),
                    self::email(EmailTemplateKey::InstructorCalendarClash, 'Instructor', 'Only when the new lessons overlap existing diary items.'),
                ]),
                self::step('Before each lesson', 'Normal lesson reminders only. No invoices, payment emails or payment reminders are sent, and the lessons are never released for non-payment.', []),
                self::step('The instructor signs the lesson off', 'The instructor collects the cash and signs off as usual, on the website or in the app, even without Stripe set up. The lesson is recorded as paid in cash and shows in the pupil\'s payments and the instructor\'s reports. No payout is sent.', [
                    self::email(EmailTemplateKey::InstructorLessonSignedOff, 'Instructor'),
                    self::email(EmailTemplateKey::LearnerLessonFeedbackRequest, 'Learner'),
                ]),
                self::step('A cash lesson is cancelled', 'The slot is freed. Nothing was paid through us, so there is no refund and the learner is told they will not be charged.', [
                    self::email(EmailTemplateKey::LearnerBookingCancelled, 'Learner'),
                ]),
            ]),

            self::workflow('slot-offer', self::GROUP_BOOKING, 'Short-notice lesson offer', 'An instructor with a free slot offers it to their existing learners, and the first to book and pay gets it.', [
                self::step('The instructor offers a free slot', 'The offer goes to the instructor\'s active learners who have the app.', [
                    self::push('Short Notice Lesson Available', 'Instructor\'s active learners'),
                ]),
                self::step('A learner accepts', "The slot is held for {$holdMinutes} minutes while they pay online.", []),
                self::step('Payment goes through', 'The same as any online booking.', $paidMessages),
                self::step('Nobody pays in time', 'The slot is released. See "Booking not paid in time".', []),
            ]),

            self::workflow('booking-for-someone-else', self::GROUP_BOOKING, 'Someone books for a learner (for example a parent)', 'When a parent or other contact books, the system sends payment emails to them and lesson emails to the learner.', [
                self::step('Payment emails', 'Anything about paying goes to the person who booked and is worded around the learner, for example "booked driving lessons for Jo Bloggs".', [
                    self::email(EmailTemplateKey::LearnerPaymentLink, 'Person who booked'),
                    self::email(EmailTemplateKey::LearnerOrderConfirmation, 'Person who booked'),
                    self::email(EmailTemplateKey::LearnerLessonPaymentReminder, 'Person who booked'),
                    self::email(EmailTemplateKey::LearnerPaymentDueSoon, 'Person who booked'),
                    self::email(EmailTemplateKey::LearnerLessonPaymentReceived, 'Person who booked'),
                ]),
                self::step('Login and lesson emails', 'The learner gets their own login, plus everything about the lessons themselves.', [
                    self::email(EmailTemplateKey::LearnerWelcome, 'Learner'),
                    self::email(EmailTemplateKey::LearnerInstructorOnWay, 'Learner'),
                    self::email(EmailTemplateKey::LearnerLessonRescheduled, 'Learner'),
                    self::email(EmailTemplateKey::LearnerBookingCancelled, 'Learner'),
                    self::email(EmailTemplateKey::LearnerLessonFeedbackRequest, 'Learner'),
                ]),
                self::step('Push notifications', 'Payment pushes only go to the learner if they own their account. The "Time to pay for your lesson" push goes to the learner\'s app either way.', []),
            ]),

            self::workflow('booking-not-paid', self::GROUP_BOOKING, 'Booking not paid in time', 'Lessons waiting for payment are held for a limited time, then released back into the diary.', [
                self::step('The hold runs out', "The system checks every minute. Online bookings are held for {$holdMinutes} minutes; instructor and head office bookings as described in their workflow.", []),
                self::step('The booking is released', 'The lessons are removed, the diary slots are freed for other learners and the booking is marked expired.', [
                    self::none('Nothing is sent to the learner or the instructor.'),
                ]),
                self::step('The learner pays after release', 'Occasionally a learner completes payment after the booking was released. The money is taken but there are no lessons.', [
                    self::adminEmail('Action Required: Refund for a Payment on a Released Booking', 'Head office'),
                ]),
            ]),
        ];
    }

    /**
     * @return list<Workflow>
     */
    private static function lessonWorkflows(): array
    {
        $recommendationsDelay = (int) config('lessons.recommendations_delay_minutes', 120);

        return [
            self::workflow('lesson-day', self::GROUP_LESSONS, 'On the day of a lesson', 'What the learner and instructor receive around the lesson itself.', [
                self::step('Before the lesson', 'There is no lesson reminder for learners.', [
                    self::none('Nothing is sent to the learner.'),
                ]),
                self::step('30 minutes before the instructor\'s first lesson of the day', 'The instructor is reminded to log their starting mileage.', [
                    self::push('Mileage reminder', 'Instructor'),
                ]),
                self::step('The instructor taps "On my way"', 'The learner is told their instructor is on the way.', [
                    self::email(EmailTemplateKey::LearnerInstructorOnWay, 'Learner'),
                    self::push('Your instructor is on the way', 'Learner'),
                ]),
                self::step('The instructor taps "I\'ve arrived"', 'The learner is told their instructor is outside.', [
                    self::email(EmailTemplateKey::LearnerInstructorArrived, 'Learner'),
                    self::push('Your instructor has arrived', 'Learner'),
                ]),
                self::step('30 minutes after the last lesson of the day', 'The instructor is reminded to log their end mileage.', [
                    self::push('Mileage reminder', 'Instructor'),
                ]),
            ]),

            self::workflow('after-lesson', self::GROUP_LESSONS, 'After each lesson', 'Once the instructor signs off a lesson, the instructor is paid and the learner is asked for feedback.', [
                self::step('The instructor signs off the lesson', 'The lesson is marked complete and the instructor\'s payout is started.', [
                    self::email(EmailTemplateKey::InstructorLessonSignedOff, 'Instructor'),
                ]),
                self::step('Straight after sign-off', 'The learner is asked how the lesson went. Not sent for bookings imported from the old system.', [
                    self::email(EmailTemplateKey::LearnerLessonFeedbackRequest, 'Learner'),
                ]),
                self::step('Weekly plans: the next lesson is invoiced', 'See "Learner books online and pays weekly".', [
                    self::email(EmailTemplateKey::LearnerLessonPaymentReminder, 'Learner, or the person who booked'),
                    self::push('Time to pay for your lesson', 'Learner'),
                ]),
                self::step("{$recommendationsDelay} minutes later", 'If the instructor wrote a lesson summary, the learner is sent learning resources that match it.', [
                    self::email(EmailTemplateKey::LearnerLessonResourceRecommendations, 'Learner'),
                ]),
                self::step('The instructor shares resources from the app', 'Any time the instructor attaches resources to a lesson.', [
                    self::email(EmailTemplateKey::LearnerLessonResourcesAssigned, 'Learner'),
                ]),
                self::step('If the instructor\'s payout cannot be sent', 'This happens if the platform\'s Stripe balance is too low. The lesson is still signed off.', [
                    self::adminEmail('Manual instructor payout required', 'Head office'),
                ]),
            ]),

            self::workflow('course-complete', self::GROUP_LESSONS, 'Last lesson and course complete', 'What happens when the final lesson of a package is signed off.', [
                self::step('The last lesson is signed off', 'Everything in "After each lesson" happens as normal, and the booking is marked complete.', [
                    self::none('Nothing extra is sent. There is no completion, test or review email.'),
                ]),
            ]),
        ];
    }

    /**
     * @return list<Workflow>
     */
    private static function changeWorkflows(): array
    {
        $lateHours = BookingPayments::lateCancellationHours();

        return [
            self::workflow('lesson-rescheduled', self::GROUP_CHANGES, 'Lesson rescheduled', 'An instructor or head office moves one lesson, or a run of upcoming lessons, to a new time.', [
                self::step('One lesson is moved', 'The learner is told the old and new times.', [
                    self::email(EmailTemplateKey::LearnerLessonRescheduled, 'Learner'),
                ]),
                self::step('A run of lessons is moved to a new weekly slot', 'Both sides get a summary of the new schedule.', [
                    self::email(EmailTemplateKey::LearnerLessonsBulkRescheduled, 'Learner'),
                    self::email(EmailTemplateKey::InstructorLessonsBulkRescheduled, 'Instructor'),
                ]),
                self::step('The learner wants to move a lesson', 'Learners cannot reschedule themselves. They contact their instructor.', []),
            ]),

            self::workflow('lessons-cancelled', self::GROUP_CHANGES, 'Lessons cancelled', 'An instructor or head office cancels one or more lessons and picks a reason, which also records who cancelled (pupil, instructor or other). Paid lessons are never refunded or paid out automatically: head office reviews each one.', [
                self::step('The lessons are cancelled', 'The diary slots are freed. The learner gets one email listing the cancelled lessons and the reason. If they had paid, it says head office will be in touch.', [
                    self::email(EmailTemplateKey::LearnerBookingCancelled, 'Learner'),
                ]),
                self::step('Lesson not paid yet', 'It is simply cancelled.', [
                    self::none('Nothing else is sent.'),
                ]),
                self::step('Lesson already paid', "Head office gets the reason, who cancelled, any details and how much notice was given (flagged late when under {$lateHours} hours). They contact the learner and instructor, then either refund the learner in Stripe (everything except the booking fee) or pay the instructor by hand.", [
                    self::adminEmail('Action Required: Review Cancelled Paid Lesson(s)', 'Head office'),
                ]),
                self::step('The learner wants to cancel', 'Learners cannot cancel themselves. They contact their instructor or head office.', []),
            ]),

            self::workflow('learner-transferred', self::GROUP_CHANGES, 'Learner moves to a new instructor', 'Head office moves a learner, and their future lessons, to a different instructor.', [
                self::step('Head office transfers the learner', 'Future lessons move to the new instructor\'s diary at the same times. Any that clash with the new instructor\'s diary are flagged for them to rebook.', [
                    self::email(EmailTemplateKey::LearnerStudentTransfer, 'Learner'),
                    self::email(EmailTemplateKey::InstructorStudentLost, 'Previous instructor'),
                    self::email(EmailTemplateKey::InstructorStudentGained, 'New instructor'),
                ]),
                self::step('Head office assigns a learner to an instructor', 'Used for learners who are not yet with an instructor.', [
                    self::email(EmailTemplateKey::InstructorStudentAssigned, 'Instructor'),
                ]),
            ]),
        ];
    }

    /**
     * @return list<Workflow>
     */
    private static function instructorWorkflows(): array
    {
        return [
            self::workflow('instructor-joins', self::GROUP_INSTRUCTORS, 'New instructor joins', 'Head office adds an instructor, who then sets a password and connects Stripe to get paid.', [
                self::step('Head office adds the instructor', 'Their account is created and they are emailed a link to set their password.', [
                    self::email(EmailTemplateKey::InstructorWelcome, 'Instructor'),
                ]),
                self::step('Head office re-sends the welcome email', 'From the instructor\'s profile, if they did not receive it.', [
                    self::email(EmailTemplateKey::InstructorWelcome, 'Instructor'),
                ]),
                self::step('Head office sends the Stripe setup link', 'The instructor connects their bank details through Stripe. They cannot sign off lessons (and be paid) until this is done.', [
                    self::email(EmailTemplateKey::InstructorStripeSetupLink, 'Instructor'),
                ]),
            ]),

            self::workflow('instructor-tax', self::GROUP_INSTRUCTORS, 'Instructor tax reminders (HMRC)', 'Reminders for instructors who use Making Tax Digital in the app.', [
                self::step('Quarterly update due', 'Sent 30, 14, 7 and 1 days before the deadline.', [
                    self::email(EmailTemplateKey::InstructorItsaDueSoon, 'Instructor'),
                    self::push('MTD ITSA — quarterly update due soon', 'Instructor'),
                ]),
                self::step('VAT return due', 'Sent 30, 14, 7 and 1 days before the deadline.', [
                    self::email(EmailTemplateKey::InstructorVatDueSoon, 'Instructor'),
                    self::push('MTD VAT — return due soon', 'Instructor'),
                ]),
                self::step('HMRC connection expiring', 'Sent 30 and 7 days before the connection needs renewing.', [
                    self::email(EmailTemplateKey::InstructorHmrcReconnect, 'Instructor'),
                    self::push('HMRC connection — action soon', 'Instructor'),
                ]),
                self::step('Tax-year archive is ready', 'When the instructor requests their year-end download.', [
                    self::email(EmailTemplateKey::InstructorYearEndArchiveReady, 'Instructor'),
                ]),
            ]),
        ];
    }

    /**
     * @return list<Workflow>
     */
    private static function accountWorkflows(): array
    {
        return [
            self::workflow('accounts-and-messages', self::GROUP_ACCOUNTS, 'Accounts, messages and deletion', 'Everything else that sends a message: new accounts, in-app messages, password resets and account deletion.', [
                self::step('A learner signs up in the app themselves', 'Their account is created.', [
                    self::email(EmailTemplateKey::LearnerRegistered, 'Learner'),
                ]),
                self::step('An instructor adds a learner', 'The learner is sent their login details.', [
                    self::email(EmailTemplateKey::LearnerWelcome, 'Learner'),
                ]),
                self::step('Someone sends an in-app message', 'Covers instructor, learner, head office and broadcast messages.', [
                    self::email(EmailTemplateKey::SharedNewMessage, 'Recipient'),
                    self::push('New message from {sender}', 'Recipient'),
                ]),
                self::step('Head office resets a learner\'s password', 'The learner is sent a new temporary password.', [
                    self::email(EmailTemplateKey::LearnerPasswordReset, 'Learner'),
                ]),
                self::step('A learner asks to delete their account', 'The account is scheduled for deletion in 30 days.', [
                    self::email(EmailTemplateKey::LearnerAccountDeletionRequested, 'Learner'),
                ]),
                self::step('They change their mind', 'The deletion is cancelled.', [
                    self::email(EmailTemplateKey::LearnerAccountDeletionCancelled, 'Learner'),
                ]),
                self::step('30 days later', 'Their personal details are removed. Future lessons stay in the diary.', [
                    self::none('Nothing is sent, and the instructor is not told.'),
                ]),
                self::step('Head office adds a new admin', 'The new admin is sent their login details.', [
                    self::email(EmailTemplateKey::AdminWelcome, 'New admin'),
                ]),
            ]),
        ];
    }

    /**
     * @param  list<WorkflowStep>  $steps
     * @return Workflow
     */
    private static function workflow(string $key, string $group, string $title, string $summary, array $steps): array
    {
        return [
            'key' => $key,
            'group' => $group,
            'title' => $title,
            'summary' => $summary,
            'steps' => $steps,
        ];
    }

    /**
     * @param  list<WorkflowMessage>  $messages
     * @return WorkflowStep
     */
    private static function step(string $when, string $what, array $messages): array
    {
        return [
            'when' => $when,
            'what' => $what,
            'messages' => $messages,
        ];
    }

    /**
     * @return WorkflowMessage
     */
    private static function email(EmailTemplateKey $template, string $recipient, ?string $note = null): array
    {
        return self::message('email', $template->value, $template->value, $recipient, $note);
    }

    /**
     * @return WorkflowMessage
     */
    private static function push(string $title, string $recipient, ?string $note = null): array
    {
        return self::message('push', null, $title, $recipient, $note);
    }

    /**
     * @return WorkflowMessage
     */
    private static function adminEmail(string $subject, string $recipient): array
    {
        return self::message('admin_email', null, $subject, $recipient, 'Fixed wording, not editable.');
    }

    /**
     * @return WorkflowMessage
     */
    private static function bird(string $label): array
    {
        return self::message('bird', null, $label, 'Bird', null);
    }

    /**
     * @return WorkflowMessage
     */
    private static function none(string $label): array
    {
        return self::message('none', null, $label, '', null);
    }

    /**
     * @param  'email'|'push'|'admin_email'|'bird'|'none'  $channel
     * @return WorkflowMessage
     */
    private static function message(string $channel, ?string $template, string $label, string $recipient, ?string $note): array
    {
        return [
            'channel' => $channel,
            'template' => $template,
            'label' => $label,
            'recipient' => $recipient,
            'note' => $note,
        ];
    }
}
