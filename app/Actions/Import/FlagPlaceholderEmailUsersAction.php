<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\User;
use App\Support\PlaceholderEmails;
use Illuminate\Database\Eloquent\Builder;

class FlagPlaceholderEmailUsersAction
{
    /**
     * Flag every not-yet-flagged user with a placeholder email as
     * `exclude_from_emails`, so the mail guard and welcome emails skip them.
     * Changes nothing else on the user; no events or emails.
     *
     * @return array{users: int, by_style: array<string, int>}
     */
    public function __invoke(bool $dryRun = false): array
    {
        $byStyle = [];

        foreach ([...PlaceholderEmails::LIKE_PATTERNS, ...PlaceholderEmails::KNOWN_FAKE_ADDRESSES] as $pattern) {
            $byStyle[$pattern] = $this->unflagged()->where('email', 'like', $pattern)->count();
        }

        $users = $this->placeholders()->count();

        if (! $dryRun && $users > 0) {
            $this->placeholders()->update(['exclude_from_emails' => true]);
        }

        return ['users' => $users, 'by_style' => $byStyle];
    }

    /**
     * @return Builder<User>
     */
    protected function placeholders(): Builder
    {
        return $this->unflagged()->where(function (Builder $query): void {
            foreach (PlaceholderEmails::LIKE_PATTERNS as $pattern) {
                $query->orWhere('email', 'like', $pattern);
            }

            $query->orWhereIn('email', PlaceholderEmails::KNOWN_FAKE_ADDRESSES);
        });
    }

    /**
     * @return Builder<User>
     */
    protected function unflagged(): Builder
    {
        return User::query()->where('exclude_from_emails', false);
    }
}
