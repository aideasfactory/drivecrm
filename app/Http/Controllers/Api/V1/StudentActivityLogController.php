<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ActivityLogResource;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentActivityLogController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    /**
     * Return paginated activity logs for the authenticated student.
     *
     * Query params: category (string|'all'), search (string), per_page (int, default 20).
     *
     * Auth: Bearer token — student only. Student is resolved from the token.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user()->student;

        $filters = [
            'category' => $request->query('category'),
            'search' => $request->query('search'),
            'per_page' => $request->query('per_page') ? (int) $request->query('per_page') : 20,
        ];

        $logs = $this->activityLogService->getStudentActivityLogs($student, $filters);

        return ActivityLogResource::collection($logs);
    }
}
