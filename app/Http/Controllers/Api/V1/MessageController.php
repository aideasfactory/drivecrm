<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Resources\V1\ConversationResource;
use App\Http\Resources\V1\MessageResource;
use App\Models\Message;
use App\Models\Student;
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
     * The mobile app sends a student ID (from the students table).
     * We resolve it to the parent user for the conversation lookup.
     * Messages are returned newest first for pagination.
     */
    public function show(Request $request, int $conversationUserId): AnonymousResourceCollection
    {
        $user = $this->resolveConversationUser($request->user(), $conversationUserId);

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
        $recipient = $this->resolveConversationUser($sender, (int) $request->validated('recipient_id'));
        $recipient->load(['instructor', 'student']);

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

    /**
     * Resolve the conversation partner's User model.
     *
     * If the authenticated user is an instructor, the ID is a student ID
     * and we look up the parent user_id from the students table.
     * If the authenticated user is a student, the ID is already a user ID.
     */
    private function resolveConversationUser(User $authUser, int $id): User
    {
        if ($authUser->isInstructor()) {
            $student = Student::findOrFail($id);

            return User::findOrFail($student->user_id);
        }

        return User::findOrFail($id);
    }
}
