<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GetDashboardMetricsAction
{
    /**
     * Build the full dashboard payload — KPIs, time series, breakdowns, and recent activity.
     */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();
        $thirtyDaysAgo = $now->subDays(30);
        $sixtyDaysAgo = $now->subDays(60);

        return [
            'kpis' => $this->kpis($now, $thirtyDaysAgo, $sixtyDaysAgo),
            'revenueTrend' => $this->weeklyRevenueTrend($now),
            'signupsTrend' => $this->weeklySignupsTrend($now),
            'paymentModeMix' => $this->paymentModeMix(),
            'orderStatusBreakdown' => $this->orderStatusBreakdown(),
            'topInstructors' => $this->topInstructors($thirtyDaysAgo),
            'latestActivity' => $this->latestActivity(),
            'latestUsers' => $this->latestUsers(),
        ];
    }

    /**
     * @return array<string, array{value: int|float, delta: float|null, label: string}>
     */
    private function kpis(CarbonImmutable $now, CarbonImmutable $thirtyDaysAgo, CarbonImmutable $sixtyDaysAgo): array
    {
        $paidStatuses = ['active', 'completed'];

        $revenue30 = (int) Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('total_price_pence');

        $revenuePrev30 = (int) Order::query()
            ->whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->sum('total_price_pence');

        $newStudents30 = Student::query()->where('created_at', '>=', $thirtyDaysAgo)->count();
        $newStudentsPrev30 = Student::query()->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();

        $lessonsBooked30 = Lesson::query()
            ->whereNotNull('date')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $lessonsBookedPrev30 = Lesson::query()
            ->whereNotNull('date')
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();

        $lessonsCompleted30 = Lesson::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $thirtyDaysAgo)
            ->count();

        $lessonsCompletedPrev30 = Lesson::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();

        $activeOrders = Order::query()->where('status', 'active')->count();
        $activeInstructors = Instructor::query()->where('status', 'active')->count();
        $activeStudents = Student::query()->where('status', 'active')->count();

        $avgOrderValue = Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->avg('total_price_pence');

        return [
            'revenue30' => [
                'label' => 'Revenue (30d)',
                'value' => $revenue30,
                'delta' => $this->percentDelta($revenue30, $revenuePrev30),
            ],
            'newStudents30' => [
                'label' => 'New Students (30d)',
                'value' => $newStudents30,
                'delta' => $this->percentDelta($newStudents30, $newStudentsPrev30),
            ],
            'lessonsBooked30' => [
                'label' => 'Lessons Booked (30d)',
                'value' => $lessonsBooked30,
                'delta' => $this->percentDelta($lessonsBooked30, $lessonsBookedPrev30),
            ],
            'lessonsCompleted30' => [
                'label' => 'Lessons Completed (30d)',
                'value' => $lessonsCompleted30,
                'delta' => $this->percentDelta($lessonsCompleted30, $lessonsCompletedPrev30),
            ],
            'activeOrders' => [
                'label' => 'Active Orders',
                'value' => $activeOrders,
                'delta' => null,
            ],
            'activeInstructors' => [
                'label' => 'Active Instructors',
                'value' => $activeInstructors,
                'delta' => null,
            ],
            'activeStudents' => [
                'label' => 'Active Students',
                'value' => $activeStudents,
                'delta' => null,
            ],
            'avgOrderValue' => [
                'label' => 'Avg Order Value (30d)',
                'value' => (int) round((float) $avgOrderValue),
                'delta' => null,
            ],
        ];
    }

    /**
     * Weekly revenue (in pence) for the last 12 weeks, oldest first.
     *
     * @return array<int, array{week: string, value: int}>
     */
    private function weeklyRevenueTrend(CarbonImmutable $now): array
    {
        $start = $now->subWeeks(11)->startOfWeek();

        $rows = Order::query()
            ->selectRaw('YEARWEEK(created_at, 3) as yw, SUM(total_price_pence) as total')
            ->whereIn('status', ['active', 'completed'])
            ->where('created_at', '>=', $start)
            ->groupBy('yw')
            ->pluck('total', 'yw');

        return $this->fillWeekly($start, 12, function (CarbonImmutable $weekStart) use ($rows) {
            $key = (int) $weekStart->format('oW');

            return (int) ($rows[$key] ?? 0);
        });
    }

    /**
     * Weekly student signups for the last 12 weeks.
     *
     * @return array<int, array{week: string, value: int}>
     */
    private function weeklySignupsTrend(CarbonImmutable $now): array
    {
        $start = $now->subWeeks(11)->startOfWeek();

        $rows = Student::query()
            ->selectRaw('YEARWEEK(created_at, 3) as yw, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('yw')
            ->pluck('total', 'yw');

        return $this->fillWeekly($start, 12, function (CarbonImmutable $weekStart) use ($rows) {
            $key = (int) $weekStart->format('oW');

            return (int) ($rows[$key] ?? 0);
        });
    }

    /**
     * @param  callable(CarbonImmutable): int  $valueFor
     * @return array<int, array{week: string, value: int}>
     */
    private function fillWeekly(CarbonImmutable $start, int $weeks, callable $valueFor): array
    {
        $out = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->addWeeks($i);
            $out[] = [
                'week' => $weekStart->format('d M'),
                'value' => $valueFor($weekStart),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{mode: string, count: int, revenue: int}>
     */
    private function paymentModeMix(): array
    {
        return Order::query()
            ->select('payment_mode', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price_pence) as revenue'))
            ->whereIn('status', ['active', 'completed'])
            ->groupBy('payment_mode')
            ->get()
            ->map(fn ($row) => [
                'mode' => $row->payment_mode instanceof \BackedEnum ? (string) $row->payment_mode->value : (string) $row->payment_mode,
                'count' => (int) $row->count,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function orderStatusBreakdown(): array
    {
        return Order::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status instanceof \BackedEnum ? (string) $row->status->value : (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, revenue: int, lessons_completed: int}>
     */
    private function topInstructors(CarbonImmutable $thirtyDaysAgo): array
    {
        return DB::table('instructors')
            ->select('instructors.id', 'users.name as instructor_name')
            ->join('users', 'users.id', '=', 'instructors.user_id')
            ->leftJoin('orders', function ($join) use ($thirtyDaysAgo) {
                $join->on('orders.instructor_id', '=', 'instructors.id')
                    ->whereIn('orders.status', ['active', 'completed'])
                    ->where('orders.created_at', '>=', $thirtyDaysAgo);
            })
            ->selectRaw('COALESCE(SUM(orders.total_price_pence), 0) as revenue')
            ->selectRaw('(SELECT COUNT(*) FROM lessons WHERE lessons.instructor_id = instructors.id AND lessons.status = "completed" AND lessons.completed_at >= ?) as lessons_completed', [$thirtyDaysAgo])
            ->groupBy('instructors.id', 'users.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->instructor_name,
                'revenue' => (int) $row->revenue,
                'lessons_completed' => (int) $row->lessons_completed,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, category: string, message: string, created_at: string, subject: string|null}>
     */
    private function latestActivity(): array
    {
        return ActivityLog::query()
            ->with('loggable.user')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (ActivityLog $log) {
                $subject = null;
                if ($log->loggable && $log->loggable->user) {
                    $subject = $log->loggable->user->name;
                }

                return [
                    'id' => (int) $log->id,
                    'category' => (string) $log->category,
                    'message' => (string) $log->message,
                    'created_at' => $log->created_at?->toIso8601String() ?? '',
                    'subject' => $subject,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, role: string, created_at: string}>
     */
    private function latestUsers(): array
    {
        return User::query()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) $u->email,
                'role' => $u->role instanceof \BackedEnum ? (string) $u->role->value : (string) $u->role,
                'created_at' => $u->created_at?->toIso8601String() ?? '',
            ])
            ->all();
    }

    private function percentDelta(int|float $current, int|float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
