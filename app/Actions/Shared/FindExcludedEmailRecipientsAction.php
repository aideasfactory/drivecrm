<?php

declare(strict_types=1);

namespace App\Actions\Shared;

use App\Models\User;
use App\Support\PlaceholderEmails;

class FindExcludedEmailRecipientsAction
{
    /**
     * Which of these addresses must never be emailed: placeholder addresses,
     * and any user flagged `exclude_from_emails`.
     *
     * @param  array<int, string>  $emails
     * @return array<int, string> The excluded addresses, lower-cased
     */
    public function __invoke(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            fn (string $email) => strtolower(trim($email)),
            $emails,
        ))));

        if ($emails === []) {
            return [];
        }

        $excluded = array_filter($emails, fn (string $email) => PlaceholderEmails::isPlaceholder($email));

        $flagged = User::query()
            ->whereIn('email', array_diff($emails, $excluded))
            ->where('exclude_from_emails', true)
            ->pluck('email')
            ->map(fn (string $email) => strtolower($email))
            ->all();

        return array_values(array_unique([...$excluded, ...$flagged]));
    }
}
