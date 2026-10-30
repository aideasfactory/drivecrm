<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\InstructorStatus;
use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetInstructorListAction
{
    public const ONBOARDING_EMAIL_SENT = 'sent';

    public const ONBOARDING_EMAIL_NOT_SENT = 'not_sent';

    public const STRIPE_CONNECTED = 'connected';

    public const STRIPE_NOT_CONNECTED = 'not_connected';

    public const AVAILABILITY_ADDED = 'added';

    public const AVAILABILITY_NOT_ADDED = 'not_added';

    /** All three setup steps done. */
    public const SETUP_COMPLETE = 'complete';

    /** At least one setup step missing — the instructors to chase. */
    public const SETUP_INCOMPLETE = 'incomplete';

    /**
     * Build the instructors list rows with their setup status, optionally
     * narrowed by search and setup filters. Every filter must match. The
     * `setup` filter is the overall one: `incomplete` = missing any step.
     * Archived (deleted) instructors are never listed.
     *
     * Setup status mirrors the instructor page:
     * - Onboarding email sent: the user's `welcome_email_pending` flag is off.
     * - Stripe connected: Instructor::isStripeConnected().
     * - Availability added: at least one open "Available" diary slot
     *   (CalendarItem::emptyAvailability) that starts in the future.
     *
     * @param  array{search?: ?string, setup?: ?string, onboarding_email?: ?string, stripe?: ?string, availability?: ?string}  $filters
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     phone: ?string,
     *     connection_status: string,
     *     onboarding_email_sent: bool,
     *     stripe_connected: bool,
     *     availability_added: bool,
     *     setup_complete: bool,
     *     pupils_count: int,
     *     last_sync: string
     * }>
     */
    public function __invoke(array $filters = []): Collection
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        $rows = Instructor::query()
            ->where('status', '!=', InstructorStatus::Archived->value)
            ->with('user')
            ->withCount('students')
            ->withExists(['calendars as has_future_availability' => fn (Builder $calendars) => $calendars
                ->whereDate('date', '>=', $today)
                ->whereHas('items', fn (Builder $items) => $items
                    ->emptyAvailability()
                    ->where(fn (Builder $query) => $query
                        ->whereDate('calendars.date', '>', $today)
                        ->orWhere('calendar_items.start_time', '>', $currentTime))),
            ])
            ->get()
            ->map(function (Instructor $instructor): array {
                $stripeConnected = $instructor->isStripeConnected();
                $onboardingEmailSent = ! $instructor->user->welcome_email_pending;
                $availabilityAdded = (bool) $instructor->has_future_availability;

                return [
                    'id' => $instructor->id,
                    'name' => $instructor->user->name,
                    'email' => $instructor->user->email,
                    'phone' => $instructor->phone,
                    'connection_status' => $stripeConnected ? 'connected' : 'not_connected',
                    'onboarding_email_sent' => $onboardingEmailSent,
                    'stripe_connected' => $stripeConnected,
                    'availability_added' => $availabilityAdded,
                    'setup_complete' => $onboardingEmailSent && $stripeConnected && $availabilityAdded,
                    'pupils_count' => (int) $instructor->students_count,
                    'last_sync' => $instructor->updated_at->diffForHumans(),
                ];
            });

        return $this->applyFilters($rows, $filters)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{search?: ?string, setup?: ?string, onboarding_email?: ?string, stripe?: ?string, availability?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        if ($search !== '') {
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['name']), $search)
                || str_contains(mb_strtolower($row['email']), $search));
        }

        $statusFilters = [
            'setup_complete' => $this->expectedFlag($filters['setup'] ?? null, self::SETUP_COMPLETE, self::SETUP_INCOMPLETE),
            'onboarding_email_sent' => $this->expectedFlag($filters['onboarding_email'] ?? null, self::ONBOARDING_EMAIL_SENT, self::ONBOARDING_EMAIL_NOT_SENT),
            'stripe_connected' => $this->expectedFlag($filters['stripe'] ?? null, self::STRIPE_CONNECTED, self::STRIPE_NOT_CONNECTED),
            'availability_added' => $this->expectedFlag($filters['availability'] ?? null, self::AVAILABILITY_ADDED, self::AVAILABILITY_NOT_ADDED),
        ];

        foreach ($statusFilters as $field => $expected) {
            if ($expected !== null) {
                $rows = $rows->filter(fn (array $row): bool => $row[$field] === $expected);
            }
        }

        return $rows;
    }

    private function expectedFlag(?string $value, string $yes, string $no): ?bool
    {
        return match ($value) {
            $yes => true,
            $no => false,
            default => null,
        };
    }
}
