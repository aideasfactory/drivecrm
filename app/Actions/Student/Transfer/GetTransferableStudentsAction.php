<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetTransferableStudentsAction
{
    /**
     * Return students eligible to be transferred (those who currently have an instructor),
     * with their current instructor's name eager-loaded for display.
     *
     * Optionally filters by a search term matched against the student's full name,
     * email addresses and phone numbers (both the student's own and the parent/guardian
     * contact fields), and caps the result set for typeahead-style lookups.
     *
     * @return Collection<int, Student>
     */
    public function __invoke(?string $search = null, ?int $limit = null): Collection
    {
        return Student::query()
            ->whereNotNull('instructor_id')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $inner) use ($like): void {
                    $inner->whereRaw("TRIM(CONCAT(first_name, ' ', surname)) LIKE ?", [$like])
                        ->orWhere('email', 'like', $like)
                        ->orWhere('contact_email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('contact_phone', 'like', $like);
                });
            })
            ->with('instructor.user:id,name')
            ->orderBy('first_name')
            ->orderBy('surname')
            ->when($limit !== null, fn (Builder $query) => $query->limit($limit))
            ->get();
    }
}
