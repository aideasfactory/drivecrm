<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an order was booked from.
 */
enum OrderSource: string
{
    /** The learner booked on the website booking form. */
    case WEBSITE = 'website';

    /** The bookings team used "Book lessons for a student". */
    case BOOKINGS_TEAM = 'bookings_team';

    /** Booked for a pupil from the web CRM. */
    case CRM = 'crm';

    /** The learner booked in the app. */
    case LEARNER_APP = 'learner_app';

    /** The instructor booked for their pupil in the app. */
    case INSTRUCTOR_APP = 'instructor_app';

    /** Lessons brought in from another system by the legacy importer. */
    case IMPORTED = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::WEBSITE => 'Website',
            self::BOOKINGS_TEAM => 'Bookings team',
            self::CRM => 'CRM',
            self::LEARNER_APP => 'Learner app',
            self::INSTRUCTOR_APP => 'Instructor app',
            self::IMPORTED => 'Imported',
        };
    }

    /**
     * Label for orders placed before the source was recorded, where the CRM,
     * learner app and instructor app can't be told apart.
     */
    public static function unknownLabel(): string
    {
        return 'Instructor / App';
    }
}
