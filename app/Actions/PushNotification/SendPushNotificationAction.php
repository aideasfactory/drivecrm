<?php

declare(strict_types=1);

namespace App\Actions\PushNotification;

use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;
use Illuminate\Support\Facades\Log;

class SendPushNotificationAction
{
    public function __invoke(PushNotification $notification): bool
    {
        $user = $notification->user;
        $token = $user->expo_push_token;

        if (! $token) {
            $notification->update([
                'status' => PushNotificationStatus::FAILED,
                'error_message' => 'User has no push token registered.',
            ]);

            return false;
        }

        try {
            $message = (new ExpoMessage([
                'title' => $notification->title,
                'body' => $notification->body,
            ]))->setSound('default');

            if ($notification->data) {
                $message->setData($notification->data);
            }

            $response = (new Expo)->send($message)->to([$token])->push();

            $responseData = $response->getData();

            if (! empty($responseData) && ($responseData[0]['status'] ?? '') === 'ok') {
                $notification->update([
                    'status' => PushNotificationStatus::SENT,
                    'sent_at' => now(),
                ]);

                return true;
            }

            $errorMessage = $responseData[0]['message'] ?? 'Unknown Expo error';

            $notification->update([
                'status' => PushNotificationStatus::FAILED,
                'error_message' => $errorMessage,
            ]);

            Log::warning('Push notification delivery failed', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $errorMessage,
            ]);

            return false;
        } catch (\Exception $e) {
            $notification->update([
                'status' => PushNotificationStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Push notification exception', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
