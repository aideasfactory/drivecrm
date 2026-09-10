<?php

declare(strict_types=1);

namespace App\Enums;

enum ResourceFolderVisibility: string
{
    case STUDENT = 'student';
    case INSTRUCTOR = 'instructor';
    case BOTH = 'both';

    /**
     * Visibility values that should appear for the given audience.
     *
     * @return list<self>
     */
    public static function visibleTo(ResourceAudience $audience): array
    {
        return match ($audience) {
            ResourceAudience::STUDENT => [self::STUDENT, self::BOTH],
            ResourceAudience::INSTRUCTOR => [self::INSTRUCTOR, self::BOTH],
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesVisibleTo(ResourceAudience $audience): array
    {
        return array_map(
            fn (self $visibility): string => $visibility->value,
            self::visibleTo($audience)
        );
    }

    public function isVisibleTo(ResourceAudience $audience): bool
    {
        return in_array($this, self::visibleTo($audience), true);
    }
}
