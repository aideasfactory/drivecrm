<?php

use App\Actions\Shared\Message\SendMessagePushNotificationAction;
use App\Enums\PushNotificationStatus;
use App\Models\Message;
use App\Models\PushNotification;
use App\Models\User;
use ExpoSDK\Expo;

test('it sends a push notification when recipient has a push token', function () {
    $sender = User::factory()->create(['name' => 'John Doe']);
    $recipient = User::factory()->create([
        'expo_push_token' => 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]',
    ]);
    $message = Message::factory()->create([
        'from' => $sender->id,
        'to' => $recipient->id,
        'message' => 'Hello there!',
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('getData')->andReturn([['status' => 'ok']]);

    $mockPush = Mockery::mock();
    $mockPush->shouldReceive('push')->andReturn($mockResponse);

    $mockExpo = Mockery::mock();
    $mockExpo->shouldReceive('send')->andReturn($mockExpo);
    $mockExpo->shouldReceive('to')->andReturn($mockPush);

    app()->bind(Expo::class, fn () => $mockExpo);

    $action = app(SendMessagePushNotificationAction::class);
    $action($message, $sender, $recipient);

    $notification = PushNotification::where('user_id', $recipient->id)->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('New message from John Doe')
        ->and($notification->body)->toBe('Hello there!')
        ->and($notification->data)->toMatchArray([
            'type' => 'new_message',
            'message_id' => $message->id,
            'sender_id' => $sender->id,
            'sender_name' => 'John Doe',
        ]);
});

test('it does not send a push notification when recipient has no push token', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'expo_push_token' => null,
    ]);
    $message = Message::factory()->create([
        'from' => $sender->id,
        'to' => $recipient->id,
        'message' => 'Hello there!',
    ]);

    $action = app(SendMessagePushNotificationAction::class);
    $action($message, $sender, $recipient);

    expect(PushNotification::where('user_id', $recipient->id)->count())->toBe(0);
});

test('it truncates long messages in push notification body', function () {
    $sender = User::factory()->create(['name' => 'Jane']);
    $recipient = User::factory()->create([
        'expo_push_token' => 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]',
    ]);
    $longMessage = str_repeat('a', 300);
    $message = Message::factory()->create([
        'from' => $sender->id,
        'to' => $recipient->id,
        'message' => $longMessage,
    ]);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('getData')->andReturn([['status' => 'ok']]);

    $mockPush = Mockery::mock();
    $mockPush->shouldReceive('push')->andReturn($mockResponse);

    $mockExpo = Mockery::mock();
    $mockExpo->shouldReceive('send')->andReturn($mockExpo);
    $mockExpo->shouldReceive('to')->andReturn($mockPush);

    app()->bind(Expo::class, fn () => $mockExpo);

    $action = app(SendMessagePushNotificationAction::class);
    $action($message, $sender, $recipient);

    $notification = PushNotification::where('user_id', $recipient->id)->first();

    expect(strlen($notification->body))->toBeLessThanOrEqual(153); // 150 + '...'
});
