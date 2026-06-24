<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendSupportReplyRequest;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportMessagesController extends Controller
{
    public function __construct(
        protected MessageService $messageService,
        protected SupportTicketService $supportTicketService
    ) {}

    public function index(Request $request): Response
    {
        $admin = $request->user();

        $annotated = $this->supportTicketService->getAnnotatedConversations($admin);

        $folder = $request->string('folder')->toString() === 'archived' ? 'archived' : 'inbox';

        $partitioned = $annotated->partition(fn (array $c): bool => $c['is_archived'] === false);
        [$inbox, $archived] = [$partitioned[0]->values(), $partitioned[1]->values()];

        $conversations = ($folder === 'archived' ? $archived : $inbox)
            ->map(fn (array $c): array => [
                'user' => [
                    'id' => $c['user']->id,
                    'name' => $c['user']->name,
                    'role' => $c['user']->role?->value,
                ],
                'latest_message' => [
                    'message' => $c['latest_message']->message,
                    'is_own' => $c['latest_message']->from === $admin->id,
                    'created_at' => $c['latest_message']->created_at?->toIso8601String(),
                ],
            ])
            ->values();

        $selectedUserId = $request->integer('user') ?: null;
        $selectedUser = $selectedUserId
            ? User::select('id', 'name', 'email', 'role')->find($selectedUserId)
            : null;

        $thread = null;
        $selectedIsArchived = false;
        if ($selectedUser) {
            $selectedIsArchived = $annotated
                ->firstWhere(fn (array $c): bool => $c['user']->id === $selectedUser->id)['is_archived'] ?? false;

            $thread = $this->messageService
                ->getConversationMessages($admin, $selectedUser, 100)
                ->getCollection()
                ->sortBy('created_at')
                ->map(fn (Message $m) => [
                    'id' => $m->id,
                    'message' => $m->message,
                    'is_own' => $m->from === $admin->id,
                    'sender_name' => $m->sender?->name,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])
                ->values();
        }

        return Inertia::render('SupportMessages/Index', [
            'conversations' => $conversations,
            'selectedUser' => $selectedUser,
            'selectedIsArchived' => $selectedIsArchived,
            'thread' => $thread,
            'folder' => $folder,
            'inboxCount' => $inbox->count(),
            'archivedCount' => $archived->count(),
        ]);
    }

    public function store(SendSupportReplyRequest $request, User $user): RedirectResponse
    {
        $this->messageService->sendMessage(
            $request->user(),
            $user,
            $request->validated('message')
        );

        return back()->with('success', 'Reply sent.');
    }

    public function archive(Request $request, User $user): RedirectResponse
    {
        $this->supportTicketService->archive($request->user(), $user);

        return redirect()
            ->route('support-messages.index')
            ->with('success', 'Ticket closed.');
    }

    public function reopen(Request $request, User $user): RedirectResponse
    {
        $this->supportTicketService->reopen($request->user(), $user);

        return redirect()
            ->route('support-messages.index')
            ->with('success', 'Ticket reopened.');
    }
}
