<?php

namespace App\Http\Controllers;

use App\Services\BirdConversationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BirdConversationController extends Controller
{
    public function __construct(
        protected BirdConversationService $birdConversationService,
    ) {}

    /**
     * Display the Bird AI Employee conversations screen.
     */
    public function index(): Response
    {
        return Inertia::render('Integrations/Bird', [
            'configured' => (bool) config('services.bird.conversations_api_key') && (bool) config('services.bird.workspace_id'),
            'channelFiltered' => (bool) config('services.bird.ai_channel_id'),
        ]);
    }

    /**
     * List mirrored conversations as JSON for the self-loading table.
     */
    public function conversations(): JsonResponse
    {
        return response()->json($this->birdConversationService->getStoredConversations());
    }

    /**
     * Full mirrored message history for a single conversation.
     */
    public function messages(string $conversationId): JsonResponse
    {
        return response()->json([
            'messages' => $this->birdConversationService->getStoredMessages($conversationId),
        ]);
    }

    /**
     * Every mirrored message with contact details, for the full CSV export.
     */
    public function allMessages(): JsonResponse
    {
        return response()->json([
            'messages' => $this->birdConversationService->getAllStoredMessages(),
        ]);
    }

    /**
     * Pull recent activity from Bird into the local mirror.
     */
    public function sync(): JsonResponse
    {
        try {
            $result = $this->birdConversationService->sync();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json($result);
    }
}
