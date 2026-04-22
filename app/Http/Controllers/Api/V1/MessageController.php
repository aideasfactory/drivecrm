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
     * Get paginated messages between the authenticated student and their instructor.
     *
     * Convenience endpoint for students — resolves the instructor automatically
     * from the student's assigned instructor_id.
     */
    public function showInstructorConversation(Request $request): AnonymousResourceCollection
    {
        $student = $request->user()->student;
        abort_unless($student?->instructor_id, 404, 'No instructor assigned.');

        $instructorUser = $student->instructor->user;

        Gate::authorize('viewConversation', [Message::class, $instructorUser]);

        $messages = $this->messageService->getConversationMessages(
            $request->user(),
            $instructorUser
        );

        return MessageResource::collection($messages);
    }

    /**
     * Send a new message to another user.
     *
     * Instructors must provide a recipient_id (student ID from students table).
     * Students may omit recipient_id — the backend resolves their assigned instructor.
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $sender = $request->user();

        if ($sender->isStudent() && ! $request->validated('recipient_id')) {
            $recipient = $this->resolveStudentInstructor($sender);
        } else {
            $recipient = $this->resolveConversationUser($sender, (int) $request->validated('recipient_id'));
        }

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
     * Owners (admin/support) are addressed directly by user id from either
     * role — instructors and students can both open a thread with the admin.
     * Otherwise the existing instructor↔student convention applies: for an
     * instructor, the id is a student id (resolve to its parent user);
     * for a student, the id is already a user id.
     */
    private function resolveConversationUser(User $authUser, int $id): User
    {
        $asUser = User::find($id);
        if ($asUser?->isOwner()) {
            return $asUser;
        }

        if ($authUser->isInstructor()) {
            $student = Student::findOrFail($id);

            return User::findOrFail($student->user_id);
        }

        return User::findOrFail($id);
    }

    /**
     * Resolve the instructor's User model from the authenticated student's record.
     *
     * Looks up the student's assigned instructor_id and returns the instructor's user.
     */
    private function resolveStudentInstructor(User $studentUser): User
    {
        $student = $studentUser->student;
        abort_unless($student?->instructor_id, 404, 'No instructor assigned.');

        return $student->instructor->user;
    }
}
