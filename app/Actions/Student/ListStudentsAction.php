<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStudentsAction
{
    /**
     * Assignment filters for the Students page.
     *
     * @var list<string>
     */
    public const ASSIGNMENT_FILTERS = ['all', 'assigned', 'unassigned'];

    /**
     * Learner Terms filters for the Students page: accepted the current version,
     * accepted only an older version, or never accepted any version.
     *
     * @var list<string>
     */
    public const TERMS_FILTERS = ['all', 'current', 'older', 'none'];

    private const PER_PAGE = 25;

    /**
     * Paginated students, newest first, filtered by assignment, Learner Terms
     * status and a free-text search over name, email, phone and instructor name.
     * Counts per assignment filter respect the search and terms filter so the
     * badges match what the tabs will show.
     *
     * @param  string  $assignment  One of self::ASSIGNMENT_FILTERS
     * @param  string  $terms  One of self::TERMS_FILTERS
     * @param  int|null  $currentTermsVersionId  Current published Learner Terms version (null = none published)
     * @return array{students: LengthAwarePaginator<int, Student>, counts: array{all: int, assigned: int, unassigned: int}}
     */
    public function __invoke(string $assignment, ?string $search = null, string $terms = 'all', ?int $currentTermsVersionId = null): array
    {
        $students = $this->filteredQuery($assignment, $search, $terms, $currentTermsVersionId)
            ->with(['instructor.user:id,name', 'latestLearnerTermsAcceptance:id,student_id,learner_terms_version_id,accepted_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $all = $this->searchQuery($search, $terms, $currentTermsVersionId)->count();
        $unassigned = $this->searchQuery($search, $terms, $currentTermsVersionId)->whereNull('instructor_id')->count();

        return [
            'students' => $students,
            'counts' => [
                'all' => $all,
                'assigned' => $all - $unassigned,
                'unassigned' => $unassigned,
            ],
        ];
    }

    /**
     * Students matching the Students page's filters and search, unordered.
     * Shared with the Students CSV export so the download matches the page.
     *
     * @param  string  $assignment  One of self::ASSIGNMENT_FILTERS
     * @param  string  $terms  One of self::TERMS_FILTERS
     * @return Builder<Student>
     */
    public function filteredQuery(string $assignment, ?string $search = null, string $terms = 'all', ?int $currentTermsVersionId = null): Builder
    {
        return $this->searchQuery($search, $terms, $currentTermsVersionId)
            ->when($assignment === 'assigned', fn (Builder $query) => $query->whereNotNull('instructor_id'))
            ->when($assignment === 'unassigned', fn (Builder $query) => $query->whereNull('instructor_id'));
    }

    /**
     * @return Builder<Student>
     */
    private function searchQuery(?string $search, string $terms = 'all', ?int $currentTermsVersionId = null): Builder
    {
        return Student::query()
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $inner) use ($like): void {
                    $inner->whereRaw("TRIM(CONCAT(first_name, ' ', surname)) LIKE ?", [$like])
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhereHas('instructor.user', fn (Builder $user) => $user->where('name', 'like', $like));
                });
            })
            ->tap(fn (Builder $query) => $this->applyTermsFilter($query, $terms, $currentTermsVersionId));
    }

    /**
     * @param  Builder<Student>  $query
     */
    private function applyTermsFilter(Builder $query, string $terms, ?int $currentTermsVersionId): void
    {
        $acceptedCurrent = fn (Builder $acceptance) => $acceptance->where('learner_terms_version_id', $currentTermsVersionId);

        match ($terms) {
            'current' => $currentTermsVersionId === null
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('learnerTermsAcceptances', $acceptedCurrent),
            'older' => $query->whereHas('learnerTermsAcceptances')
                ->when($currentTermsVersionId !== null, fn (Builder $q) => $q->whereDoesntHave('learnerTermsAcceptances', $acceptedCurrent)),
            'none' => $query->whereDoesntHave('learnerTermsAcceptances'),
            default => null,
        };
    }
}
