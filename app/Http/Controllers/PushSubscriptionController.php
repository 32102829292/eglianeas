<?php

namespace App\Http\Controllers;

use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class PushSubscriptionController extends Controller
{
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function subscribe(Request $request): Response
    {
        $request->validate([
            'endpoint' => ['required', 'url'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = $request->user();
        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
        );

        return response()->noContent();
    }

    public function unsubscribe(Request $request): Response
    {
        $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        $request->user()->deletePushSubscription($request->input('endpoint'));

        return response()->noContent();
    }

    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->pushSubscriptions->isEmpty()) {
            return response()->json([
                'sent' => false,
                'message' => 'No push device found — enable push notifications first.',
            ], 403);
        }

        $key = 'push-test:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json([
                'sent' => false,
                'message' => 'Please wait before sending another test.',
                'retryAfter' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, 10);

        $sent = PushNotificationService::send(
            $user,
            'Test notification',
            'If you can see this, push notifications are working!',
            route('security.index')
        );

        return response()->json(['sent' => $sent]);
    }
}
