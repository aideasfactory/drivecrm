<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stand-in email addresses for imported pupils who had no real one. Every
 * user needs an email to exist, but these must never be sent anything:
 * users with one are flagged `exclude_from_emails`, and the mail guard
 * (DropMailToExcludedRecipients) drops any message addressed to one.
 */
class PlaceholderEmails
{
    /** Domain for placeholders made by the importer from now on. */
    public const DOMAIN = 'drive-plus.co.uk';

    /**
     * Fake addresses the legacy system itself used (seen in its exports).
     *
     * @var array<int, string>
     */
    public const KNOWN_FAKE_ADDRESSES = [
        'noemailneeded@gmail.com',
    ];

    /**
     * SQL LIKE patterns matching every placeholder style in the database:
     * `no-email+stu-1@drive-plus.co.uk`, the first import's
     * `no-email+stu-1@just-drive.co.uk`, and the legacy system's generated
     * `example@<id>-student.com`.
     *
     * @var array<int, string>
     */
    public const LIKE_PATTERNS = [
        'no-email+%',
        'example@%-student.com',
    ];

    /**
     * The placeholder for a pupil with no real email, built from their legacy
     * ref so it is unique and stable across re-imports.
     */
    public static function forRef(string $sourceRef): string
    {
        $localPart = preg_replace('/[^a-z0-9._-]+/', '-', strtolower(trim($sourceRef)));

        return 'no-email+'.trim((string) $localPart, '-').'@'.self::DOMAIN;
    }

    /**
     * Whether an address is a placeholder (ours or the legacy system's) rather
     * than a real inbox.
     */
    public static function isPlaceholder(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        return str_starts_with($email, 'no-email+')
            || (bool) preg_match('/^example@[^@]+-student\.com$/', $email)
            || in_array($email, self::KNOWN_FAKE_ADDRESSES, true);
    }
}
