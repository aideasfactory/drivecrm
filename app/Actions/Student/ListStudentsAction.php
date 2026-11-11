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

    private const PER_PAGE = 25;

    /**
     * Paginated students, newest first, filtered by assignment and a free-text
     * search over name, email, phone and instructor name. Counts per assignment
     * filter respect the search so the badges match what the tabs will show.
     *
     * @param  string  $assignment  One of self::ASSIGNMENT_FILTERS
     * @return array{students: LengthAwarePaginator<int, Student>, counts: array{all: int, assigned: int, unassigned: int}}
     */
    public function __invoke(string $assignment, ?string $search = null): array
    {
        $students = $this->searchQuery($search)
            ->when($assignment === 'assigned', fn (Builder $query) => $query->whereNotNull('instructor_id'))
            ->when($assignment === 'unassigned', fn (Builder $query) => $query->whereNull('instructor_id'))
            ->with(['instructor.user:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $all = $this->searchQuery($search)->count();
        $unassigned = $this->searchQuery($search)->whereNull('instructor_id')->count();

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
     * @return Builder<Student>
     */
    private function searchQuery(?string $search): Builder
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
            });
    }
}
