<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public static function send(User $user, string $title, string $body, string $url = '/'): bool
    {
        try {
            $count = $user->pushSubscriptions->count();

            if ($count === 0) {
                Log::info('[PushNotificationService] no subscriptions for user ' . $user->id . '; in-app only, push skipped', [
                    'title' => $title,
                ]);

                return false;
            }

            $user->notify(new PushNotification($title, $body, $url));

            Log::info('[PushNotificationService] push queued for user ' . $user->id . ' with ' . $count . ' subscription(s)', [
                'title' => $title,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[PushNotificationService] send failed for user ' . $user->id . ': ' . $e->getMessage(), [
                'title' => $title,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

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
