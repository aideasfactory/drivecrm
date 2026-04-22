<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendSupportReplyRequest;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportMessagesController extends Controller
{
    public function __construct(
        protected MessageService $messageService
    ) {}

    public function index(Request $request): Response
    {
        $admin = $request->user();

        $conversations = $this->messageService->getConversations($admin)
            ->map(fn (array $c) => [
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
        if ($selectedUser) {
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
            'thread' => $thread,
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
}
