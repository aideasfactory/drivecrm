<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetPendingImportedUsersAction
{
    /**
     * Imported users whose welcome email has not gone out yet, instructors
     * first so each instructor hears before their students do.
     *
     * @param  array<int, int>  $instructorIds  Limit to these instructors and their students (empty = everyone)
     * @param  string  $only  `all`, `instructors` or `students`
     * @return Collection<int, User>
     */
    public function __invoke(array $instructorIds = [], string $only = 'all'): Collection
    {
        $roles = match ($only) {
            'instructors' => [UserRole::INSTRUCTOR],
            'students' => [UserRole::STUDENT],
            default => [UserRole::INSTRUCTOR, UserRole::STUDENT],
        };

        return User::query()
            ->whereNotNull('imported_at')
            ->where('welcome_email_pending', true)
            ->whereIn('role', $roles)
            ->when($instructorIds !== [], fn (Builder $query) => $query->where(
                fn (Builder $scoped) => $scoped
                    ->whereHas('instructor', fn (Builder $instructor) => $instructor->whereIn('id', $instructorIds))
                    ->orWhereHas('student', fn (Builder $student) => $student->whereIn('instructor_id', $instructorIds))
            ))
            ->with(['instructor', 'student.instructor'])
            ->orderByRaw('role = ? desc', [UserRole::INSTRUCTOR->value])
            ->orderBy('id')
            ->get();
    }
}
