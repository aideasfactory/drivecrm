<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Resources\V1\ConversationResource;
use App\Http\Resources\V1\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messageService
    ) {}

    /**
     * List all conversations for the authenticated user.
     *
     * Returns conversations grouped by the other participant,
     * with the latest message preview, ordered by most recent first.
     */
    public function conversations(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Message::class);

        $conversations = $this->messageService->getConversations($request->user());

        return ConversationResource::collection($conversations);
    }

    /**
     * Get paginated messages in a conversation with another user.
     *
     * Messages are returned newest first for pagination.
     * The mobile app should reverse the order for chronological display.
     */
    public function show(Request $request, User $user): AnonymousResourceCollection
    {
        Gate::authorize('viewConversation', [Message::class, $user]);

        $messages = $this->messageService->getConversationMessages(
            $request->user(),
            $user
        );

        return MessageResource::collection($messages);
    }

    /**
     * Send a new message to another user.
     *
     * Authorization ensures only instructor-student pairs can message each other.
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $sender = $request->user();
        $recipient = User::with(['instructor', 'student'])->findOrFail($request->validated('recipient_id'));

        Gate::authorize('send', [Message::class, $recipient]);

        // Resolve student and instructor entities for activity logging
        if ($sender->isInstructor()) {
            $instructor = $sender->instructor;
            $student = $recipient->student;
        } else {
            $student = $sender->student;
            $instructor = $recipient->instructor;
        }

        $message = $this->messageService->sendMessage(
            $sender,
            $recipient,
            $request->validated('message'),
            $student,
            $instructor
        );

        $message->load('sender:id,name');

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}
