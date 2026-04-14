<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendPushNotificationRequest;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PushNotificationController extends Controller
{
    public function __construct(
        protected PushNotificationService $pushNotificationService
    ) {}

    public function index(): Response
    {
        $usersWithTokens = User::whereNotNull('expo_push_token')
            ->where('expo_push_token', '!=', '')
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('PushNotifications/Index', [
            'users' => $usersWithTokens,
        ]);
    }

    public function store(SendPushNotificationRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->validated('user_id'));

        $this->pushNotificationService->queue(
            $user,
            $request->validated('title'),
            $request->validated('body'),
        );

        return back()->with('success', 'Push notification queued successfully.');
    }
}
