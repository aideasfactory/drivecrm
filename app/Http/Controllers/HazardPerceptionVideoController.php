<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreHazardPerceptionVideoRequest;
use App\Http\Requests\UpdateHazardPerceptionVideoRequest;
use App\Models\HazardPerceptionVideo;
use App\Services\HazardPerceptionService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class HazardPerceptionVideoController extends Controller
{
    public function __construct(
        protected HazardPerceptionService $hazardPerceptionService
    ) {}

    /**
     * Display the hazard perception admin page.
     */
    public function index(): Response
    {
        return Inertia::render('HazardPerception/Index');
    }

    /**
     * Get all videos with their scoring zones as JSON.
     */
    public function list(): JsonResponse
    {
        return response()->json([
            'videos' => $this->hazardPerceptionService->getVideosForAdmin(),
        ]);
    }

    /**
     * Upload a new hazard perception video.
     */
    public function store(StoreHazardPerceptionVideoRequest $request): JsonResponse
    {
        $video = $this->hazardPerceptionService->createVideo(
            $this->videoData($request->validated()),
            $request->file('video'),
            $request->file('thumbnail'),
            $request->file('recap_video'),
        );

        return response()->json([
            'message' => 'Hazard perception video uploaded successfully.',
            'video' => $video,
        ], 201);
    }

    /**
     * Update a video's details, scoring zones, and optionally its files.
     */
    public function update(UpdateHazardPerceptionVideoRequest $request, HazardPerceptionVideo $hazardPerceptionVideo): JsonResponse
    {
        $video = $this->hazardPerceptionService->updateVideo(
            $hazardPerceptionVideo,
            $this->videoData($request->validated()),
            $request->file('video'),
            $request->file('thumbnail'),
            $request->file('recap_video'),
        );

        return response()->json([
            'message' => 'Hazard perception video updated successfully.',
            'video' => $video,
        ]);
    }

    /**
     * Delete a video, its scoring zones, and its uploaded files.
     */
    public function destroy(HazardPerceptionVideo $hazardPerceptionVideo): JsonResponse
    {
        $this->hazardPerceptionService->deleteVideo($hazardPerceptionVideo);

        return response()->json([
            'message' => 'Hazard perception video deleted successfully.',
        ]);
    }

    /**
     * Normalize validated input into the shape the Actions expect.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function videoData(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'topic' => $validated['topic'],
            'duration_seconds' => (int) $validated['duration_seconds'],
            'is_double_hazard' => (bool) $validated['is_double_hazard'],
            'has_recap' => (bool) $validated['has_recap'],
            'zones' => array_map(fn (array $zone): array => [
                'hazard_number' => (int) $zone['hazard_number'],
                'score' => (int) $zone['score'],
                'start_seconds' => (float) $zone['start_seconds'],
                'end_seconds' => (float) $zone['end_seconds'],
            ], $validated['zones']),
        ];
    }
}
