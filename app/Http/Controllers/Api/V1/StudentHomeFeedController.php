<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentHomeFeedResource;
use App\Services\StudentService;
use Illuminate\Http\Request;

class StudentHomeFeedController extends Controller
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * Get the student home page feed.
     *
     * Returns instructor assignment, upcoming lessons, special offer,
     * purchased hours, learning resources, and instructor bio data.
     */
    public function __invoke(Request $request): StudentHomeFeedResource
    {
        $student = $request->user()->student;

        $feed = $this->studentService->getHomeFeed($student);

        return new StudentHomeFeedResource($feed);
    }
}
