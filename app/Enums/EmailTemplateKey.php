<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailTemplateKey: string
{
    case LearnerWelcome = 'learner.welcome';
    case LearnerRegistered = 'learner.registered';
    case LearnerOrderConfirmation = 'learner.order_confirmation';
    case LearnerPaymentLink = 'learner.payment_link';
    case LearnerPaymentDueSoon = 'learner.payment_due_soon';
    case LearnerBookingCancelled = 'learner.booking_cancelled';
    case LearnerLessonSignedOff = 'learner.lesson_signed_off';
    case LearnerLessonsBulkRescheduled = 'learner.lessons_bulk_rescheduled';
    case LearnerLessonRescheduled = 'learner.lesson_rescheduled';
    case LearnerLessonPaymentReminder = 'learner.lesson_payment_reminder';
    case LearnerLessonPaymentReceived = 'learner.lesson_payment_received';
    case LearnerInstructorOnWay = 'learner.instructor_on_way';
    case LearnerInstructorArrived = 'learner.instructor_arrived';
    case LearnerStudentTransfer = 'learner.student_transfer';
    case LearnerPasswordReset = 'learner.password_reset';
    case LearnerAccountDeletionRequested = 'learner.account_deletion_requested';
    case LearnerAccountDeletionCancelled = 'learner.account_deletion_cancelled';
    case LearnerLessonResourcesAssigned = 'learner.lesson_resources_assigned';
    case LearnerLessonResourceRecommendations = 'learner.lesson_resource_recommendations';
    case LearnerLessonFeedbackRequest = 'learner.lesson_feedback_request';
    case InstructorWelcome = 'instructor.welcome';
    case InstructorStudentAssigned = 'instructor.student_assigned';
    case InstructorStudentGained = 'instructor.student_gained';
    case InstructorStudentLost = 'instructor.student_lost';
    case InstructorLessonsBulkRescheduled = 'instructor.lessons_bulk_rescheduled';
    case InstructorLessonPaymentReceived = 'instructor.lesson_payment_received';
    case InstructorLessonSignedOff = 'instructor.lesson_signed_off';
    case InstructorCalendarClash = 'instructor.calendar_clash';
    case InstructorVatDueSoon = 'instructor.vat_due_soon';
    case InstructorItsaDueSoon = 'instructor.itsa_due_soon';
    case InstructorHmrcReconnect = 'instructor.hmrc_reconnect';
    case InstructorYearEndArchiveReady = 'instructor.year_end_archive_ready';
    case SharedNewMessage = 'shared.new_message';
}
