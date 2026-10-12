<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admin-area access level for users with the owner role.
 * Only meaningful when `users.role` is `owner`.
 */
enum OwnerAccess: string
{
    case All = 'all';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All access',
            self::Restricted => 'Restricted',
        };
    }
}
