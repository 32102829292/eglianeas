<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushMessage;

class PushNotificationService
{
    public static function send(User $user, string $title, string $body, string $url = '/'): bool
    {
        try {
            $subscriptions = $user->pushSubscriptions;

            if ($subscriptions->isEmpty()) {
                return false;
            }

            foreach ($subscriptions as $subscription) {
                $notification = (new WebPushMessage())
                    ->title($title)
                    ->body($body)
                    ->icon('/pwa-icons/icon-192.png')
                    ->badge('/pwa-icons/icon-32.png')
                    ->data(['url' => $url])
                    ->vibrate([200, 100, 200]);

                $subscription->send($notification);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[PushNotificationService] send failed for user ' . $user->id . ': ' . $e->getMessage());
            return false;
        }
    }

    public static function sendToClients(string $title, string $body, string $url = '/'): int
    {
        $users = User::where('role', User::ROLE_CLIENT)->get();
        $sent = 0;

        foreach ($users as $user) {
            if (self::send($user, $title, $body, $url)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function sendToAdmins(string $title, string $body, string $url = '/'): int
    {
        $admins = User::where('role', User::ROLE_ADMIN)->get();
        $sent = 0;

        foreach ($admins as $admin) {
            if (self::send($admin, $title, $body, $url)) {
                $sent++;
            }
        }

        return $sent;
    }
}
