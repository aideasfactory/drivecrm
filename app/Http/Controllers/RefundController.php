<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService,
    ) {}

    public function index(Request $request): Response
    {
        $status = $this->filterStatus($request);

        $refunds = $this->refundService
            ->paginate($status)
            ->through(fn (Refund $refund) => $this->serialize($refund));

        return Inertia::render('Refunds/Index', [
            'refunds' => $refunds,
            'totals' => $this->refundService->totals(),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function process(Refund $refund): RedirectResponse
    {
        try {
            $completed = $this->refundService->processStripeRefund($refund, request()->user());

            return back()->with('success', $completed->paperTrail() ?? 'Refund issued via Stripe.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Refund $refund): RedirectResponse
    {
        try {
            $completed = $this->refundService->markComplete($refund, request()->user());

            return back()->with('success', $completed->paperTrail() ?? 'Refund marked complete.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Refund $refund): array
    {
        $student = $refund->student;
        $studentName = $student
            ? trim($student->first_name.' '.$student->surname)
            : 'Unknown student';

        $lesson = $refund->lesson;
        $lessonDate = $lesson?->date?->format('Y-m-d');
        $lessonTime = ($lesson?->start_time && $lesson?->end_time)
            ? $lesson->start_time->format('H:i').' – '.$lesson->end_time->format('H:i')
            : null;

        return [
            'id' => $refund->id,
            'status' => $refund->status->value,
            'method' => $refund->method?->value,
            'amount_pence' => $refund->amount_pence,
            'formatted_amount' => $refund->formatted_amount,
            'reason' => $refund->reason,
            'stripe_refund_id' => $refund->stripe_refund_id,
            'requested_at' => $refund->requested_at?->toIso8601String(),
            'completed_at' => $refund->completed_at?->toIso8601String(),
            'paper_trail' => $refund->paperTrail(),
            'requested_by' => $refund->requestedBy?->name,
            'processed_by' => $refund->processedBy?->name,
            'student' => [
                'id' => $student?->id,
                'name' => $studentName,
            ],
            'instructor' => [
                'id' => $refund->instructor_id,
                'name' => $refund->instructor?->user?->name,
            ],
            'lesson' => [
                'id' => $lesson?->id,
                'date' => $lessonDate,
                'time' => $lessonTime,
            ],
            'order_id' => $refund->order_id,
        ];
    }

    protected function filterStatus(Request $request): string
    {
        $status = (string) $request->query('status', 'all');

        return in_array($status, ['all', 'pending', 'completed'], true) ? $status : 'all';
    }
}
